import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../../core/network/realtime_stream_service.dart';
import '../../../core/storage/storage_service.dart';
import '../../../data/models/chat_message_model.dart';
import '../../../data/repositories/chat_repository.dart';

class ChatController extends GetxController {
  ChatController({required this.missionId});

  final int missionId;
  final ChatRepository _repo = ChatRepository();
  final ImagePicker _picker = ImagePicker();
  late final AudioRecorder _audioRecorder;

  final messages = <ChatMessageModel>[].obs;
  final isLoading = false.obs;
  final isSending = false.obs;
  final isFunded = false.obs;
  final chatMode = 'unlimited'.obs;
  final isRecording = false.obs;

  final textController = TextEditingController();
  final scrollController = ScrollController();

  Timer? _pollingTimer;
  int? currentUserId;
  RealtimeStreamService? _streamService;
  StreamSubscription<RealtimeEvent>? _streamSubscription;

  @override
  void onInit() {
    super.onInit();
    _audioRecorder = AudioRecorder();
    currentUserId = StorageService.getUserId();
    loadMessages();

    // Connexion au flux SSE temps réel
    _streamService = RealtimeStreamService(missionId: missionId);
    _streamSubscription = _streamService!.events.listen((event) {
      if (event.type == 'chat_message') {
        try {
          final newMsg = ChatMessageModel.fromJson(event.data);
          if (!messages.any((m) => m.id == newMsg.id)) {
            messages.add(newMsg);
            _scrollToBottom();
          }
        } catch (_) {}
      }
    });
    _streamService!.start();

    // Polling toutes les 8 secondes en filet de sécurité
    _pollingTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      _pollNewMessages();
    });
  }

  @override
  void onClose() {
    _pollingTimer?.cancel();
    _streamSubscription?.cancel();
    _streamService?.dispose();
    _audioRecorder.dispose();
    textController.dispose();
    scrollController.dispose();
    super.onClose();
  }

  Future<void> loadMessages() async {
    isLoading.value = true;
    try {
      final res = await _repo.fetchMessages(missionId);
      isFunded.value = res['is_funded'] as bool? ?? false;
      chatMode.value = res['chat_mode'] as String? ?? 'unlimited';
      messages.value = res['messages'] as List<ChatMessageModel>;
      _scrollToBottom();
    } catch (e) {
      debugPrint('[ChatController] Erreur chargement messages : $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _pollNewMessages() async {
    if (isLoading.value || isSending.value) return;
    try {
      final res = await _repo.fetchMessages(missionId);
      final newMessages = res['messages'] as List<ChatMessageModel>;
      isFunded.value = res['is_funded'] as bool? ?? false;

      if (newMessages.length != messages.length) {
        messages.value = newMessages;
        _scrollToBottom();
      }
    } catch (_) {}
  }

  Future<void> sendTextMessage() async {
    final text = textController.text.trim();
    if (text.isEmpty || isSending.value) return;

    textController.clear();
    isSending.value = true;

    try {
      final sent = await _repo.sendMessage(
        missionId: missionId,
        content: text,
        type: 'text',
      );
      messages.add(sent);
      _scrollToBottom();

      if (sent.isRedacted) {
        Get.snackbar(
          'Message sécurisé',
          'Des coordonnées ont été masquées avant la validation du devis.',
          snackPosition: SnackPosition.TOP,
          backgroundColor: const Color(0xFFFEF3C7),
          colorText: const Color(0xFF92400E),
        );
      }
    } catch (e) {
      Get.snackbar(
        'Erreur d\'envoi',
        'Impossible d\'envoyer le message. Veuillez réessayer.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isSending.value = false;
    }
  }

  Future<void> pickAndSendImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );
      if (image == null) return;

      isSending.value = true;
      final sent = await _repo.sendMessage(
        missionId: missionId,
        filePath: image.path,
        type: 'image',
      );
      messages.add(sent);
      _scrollToBottom();
    } catch (e) {
      Get.snackbar(
        'Erreur photo',
        'Échec de l\'envoi de la photo.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isSending.value = false;
    }
  }

  Future<void> toggleAudioRecording() async {
    if (isRecording.value) {
      await _stopAndSendAudio();
    } else {
      await _startAudioRecording();
    }
  }

  Future<void> _startAudioRecording() async {
    try {
      if (await _audioRecorder.hasPermission()) {
        final temp = await getTemporaryDirectory();
        final path =
            '${temp.path}/chat_memo_${DateTime.now().millisecondsSinceEpoch}.m4a';

        await _audioRecorder.start(
          const RecordConfig(encoder: AudioEncoder.aacLc),
          path: path,
        );
        isRecording.value = true;
      }
    } catch (e) {
      Get.snackbar(
        'Microphone requis',
        'Impossible de démarrer l\'enregistrement.',
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _stopAndSendAudio() async {
    try {
      final path = await _audioRecorder.stop();
      isRecording.value = false;

      if (path != null) {
        isSending.value = true;
        final sent = await _repo.sendMessage(
          missionId: missionId,
          filePath: path,
          type: 'audio',
        );
        messages.add(sent);
        _scrollToBottom();
      }
    } catch (e) {
      isRecording.value = false;
    } finally {
      isSending.value = false;
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (scrollController.hasClients) {
        scrollController.animateTo(
          scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }
}
