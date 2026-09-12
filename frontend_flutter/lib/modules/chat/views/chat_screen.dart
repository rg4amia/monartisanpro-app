import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../data/models/chat_message_model.dart';
import '../../../shared/widgets/image_viewer.dart';
import '../controllers/chat_controller.dart';

class ChatScreen extends StatelessWidget {
  const ChatScreen({required this.missionId, super.key});

  final int missionId;

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(
      ChatController(missionId: missionId),
      tag: 'chat_$missionId',
    );

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Discussion Chantier #$missionId',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            Obx(() {
              final isFunded = controller.isFunded.value;
              return Row(
                children: [
                  Icon(
                    isFunded ? Icons.lock_open : Icons.shield_outlined,
                    size: 12,
                    color: isFunded ? Colors.greenAccent : Colors.orangeAccent,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    isFunded
                        ? 'Discussion ouverte'
                        : 'Anti-contournement actif',
                    style: TextStyle(
                      fontSize: 11,
                      color: isFunded
                          ? Colors.green.shade100
                          : Colors.orange.shade100,
                    ),
                  ),
                ],
              );
            }),
          ],
        ),
        backgroundColor: const Color(0xFF1E1B4B),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Column(
        children: [
          // Bandeau explicatif anti-contournement avant devis financé
          Obx(() {
            if (controller.isFunded.value) return const SizedBox.shrink();

            return Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              color: const Color(0xFFFFFBEB),
              child: const Row(
                children: [
                  Icon(Icons.info_outline, size: 16, color: Color(0xFFB45309)),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Échangez sur les aspects techniques. Les numéros et coordonnées sont masqués jusqu\'au paiement du devis.',
                      style: TextStyle(fontSize: 11, color: Color(0xFF92400E)),
                    ),
                  ),
                ],
              ),
            );
          }),

          // Liste des messages
          Expanded(
            child: Obx(() {
              if (controller.isLoading.value && controller.messages.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }

              if (controller.messages.isEmpty) {
                return Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.chat_bubble_outline,
                          size: 52,
                          color: Colors.grey.shade400,
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'Aucun message pour l\'instant',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF4B5563),
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Posez vos questions techniques sur le chantier ou échangez des précisions sur les travaux.',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 13, color: Color(0xFF9CA3AF)),
                        ),
                      ],
                    ),
                  ),
                );
              }

              return ListView.builder(
                controller: controller.scrollController,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                itemCount: controller.messages.length,
                itemBuilder: (context, index) {
                  final message = controller.messages[index];
                  final isMe = message.senderId == controller.currentUserId;
                  return _MessageBubble(message: message, isMe: isMe);
                },
              );
            }),
          ),

          // Barre d'envoi inférieure
          _ChatInputBar(controller: controller),
        ],
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message, required this.isMe});

  final ChatMessageModel message;
  final bool isMe;

  @override
  Widget build(BuildContext context) {
    final timeStr = DateFormat('HH:mm').format(message.createdAt);

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMe ? const Color(0xFF4F46E5) : Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMe ? 16 : 4),
            bottomRight: Radius.circular(isMe ? 4 : 16),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment:
              isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Expéditeur si ce n'est pas moi
            if (!isMe && message.senderName != null) ...[
              Text(
                message.senderName!,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF4F46E5),
                ),
              ),
              const SizedBox(height: 4),
            ],

            // Contenu du message selon son type
            if (message.isText)
              Text(
                message.content ?? '',
                style: TextStyle(
                  fontSize: 14,
                  color: isMe ? Colors.white : const Color(0xFF1F2937),
                  height: 1.35,
                ),
              )
            else if (message.isImage && message.mediaUrl != null)
              // Les photos de chantier échangées servent de preuve : elles
              // doivent pouvoir être agrandies et zoomées, pas seulement vues
              // en vignette dans la bulle.
              GestureDetector(
                onTap: () => openImageViewer(
                  context,
                  urls: [message.mediaUrl!],
                  title: 'Photo de chantier',
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Stack(
                    alignment: Alignment.bottomRight,
                    children: [
                      CachedNetworkImage(
                        imageUrl: message.mediaUrl!,
                        placeholder: (ctx, url) => const SizedBox(
                          width: 140,
                          height: 140,
                          child: Center(
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        ),
                        errorWidget: (ctx, url, err) =>
                            const Icon(Icons.broken_image),
                      ),
                      const Padding(
                        padding: EdgeInsets.all(6),
                        child: CircleAvatar(
                          radius: 13,
                          backgroundColor: Colors.black54,
                          child: Icon(
                            Icons.zoom_out_map,
                            size: 15,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else if (message.isAudio)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.audiotrack,
                    size: 20,
                    color: isMe ? Colors.white : const Color(0xFF4F46E5),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Message vocal',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: isMe ? Colors.white : const Color(0xFF1F2937),
                    ),
                  ),
                ],
              ),

            // Badge si le message a été censuré/masqué par l'anti-contournement
            if (message.isRedacted) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: isMe
                      ? Colors.indigo.shade800
                      : const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.shield,
                      size: 11,
                      color: isMe ? Colors.white70 : const Color(0xFFB45309),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      'Coordonnées masquées',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: isMe ? Colors.white70 : const Color(0xFFB45309),
                      ),
                    ),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 4),
            Text(
              timeStr,
              style: TextStyle(
                fontSize: 10,
                color: isMe ? Colors.white60 : Colors.grey.shade400,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatInputBar extends StatelessWidget {
  const _ChatInputBar({required this.controller});

  final ChatController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          children: [
            // Bouton photo
            IconButton(
              icon: const Icon(Icons.photo_camera, color: Color(0xFF4F46E5)),
              onPressed: () => controller.pickAndSendImage(),
            ),

            // Bouton micro
            Obx(() {
              final isRec = controller.isRecording.value;
              return IconButton(
                icon: Icon(
                  isRec ? Icons.stop : Icons.mic,
                  color: isRec ? Colors.red : const Color(0xFF4F46E5),
                ),
                onPressed: () => controller.toggleAudioRecording(),
              );
            }),

            // Champ texte
            Expanded(
              child: TextField(
                controller: controller.textController,
                decoration: InputDecoration(
                  hintText: 'Écrire un message...',
                  hintStyle: TextStyle(fontSize: 14, color: Colors.grey.shade400),
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  filled: true,
                  fillColor: const Color(0xFFF3F4F6),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                ),
                minLines: 1,
                maxLines: 4,
                onSubmitted: (_) => controller.sendTextMessage(),
              ),
            ),
            const SizedBox(width: 6),

            // Bouton envoyer
            Obx(() {
              final isSending = controller.isSending.value;
              return CircleAvatar(
                backgroundColor: const Color(0xFF4F46E5),
                radius: 20,
                child: isSending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : IconButton(
                        icon: const Icon(Icons.send, size: 18, color: Colors.white),
                        onPressed: () => controller.sendTextMessage(),
                      ),
              );
            }),
          ],
        ),
      ),
    );
  }
}
