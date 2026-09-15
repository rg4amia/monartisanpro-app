import 'dart:async';

import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/faq_model.dart';
import '../../../data/models/support_contact_model.dart';
import '../../../data/repositories/support_repository.dart';

class SupportController extends GetxController {
  final SupportRepository _repo = SupportRepository();

  final faqs = <FaqModel>[].obs;
  final contact = Rx<SupportContactModel>(
    SupportContactModel.fromSettings(const {}),
  );
  final isLoading = false.obs;
  final expandedId = Rx<int?>(null);

  String get _role => StorageService.getRole() ?? 'client';

  @override
  void onInit() {
    super.onInit();
    unawaited(load());
  }

  Future<void> load({bool forceRefresh = false}) async {
    isLoading.value = true;
    try {
      final loadedFaqs = await _repo.getFaqs(
        role: _role,
        forceRefresh: forceRefresh,
      );
      final loadedContact = await _repo.getContactSettings();
      faqs.value = loadedFaqs;
      contact.value = loadedContact;
    } finally {
      isLoading.value = false;
    }
  }

  void toggleExpanded(int id) {
    expandedId.value = expandedId.value == id ? null : id;
  }

  Future<void> openWhatsapp() async {
    final c = contact.value;
    if (!c.hasWhatsapp) return;
    final digits = c.whatsappPhone.replaceAll(RegExp(r'[^0-9]'), '');
    final uri = Uri.parse(
      'https://api.whatsapp.com/send/?phone=$digits'
      '&text=${Uri.encodeComponent(c.whatsappMessage)}'
      '&type=phone_number&app_absent=0',
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> callSupport() async {
    final c = contact.value;
    if (!c.hasPhone) return;
    await launchUrl(
      Uri.parse('tel:${c.contactPhone}'),
      mode: LaunchMode.externalApplication,
    );
  }

  Future<void> emailSupport() async {
    final c = contact.value;
    if (!c.hasEmail) return;
    await launchUrl(
      Uri.parse('mailto:${c.contactEmail}'),
      mode: LaunchMode.externalApplication,
    );
  }
}
