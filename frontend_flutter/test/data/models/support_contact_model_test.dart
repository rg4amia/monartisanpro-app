import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/support_contact_model.dart';

void main() {
  group('SupportContactModel', () {
    test('reads all channels from a full settings payload', () {
      final contact = SupportContactModel.fromSettings({
        'whatsapp_widget_enabled': '1',
        'whatsapp_widget_phone': '+2250160606183',
        'whatsapp_widget_message': 'Bonjour, j\'ai besoin d\'aide.',
        'contact_phone': '+2250160606183',
        'contact_email': 'info@prosartisan.net',
      });

      expect(contact.hasWhatsapp, isTrue);
      expect(contact.whatsappPhone, '+2250160606183');
      expect(contact.hasPhone, isTrue);
      expect(contact.hasEmail, isTrue);
    });

    test('disables WhatsApp when the widget is turned off', () {
      final contact = SupportContactModel.fromSettings({
        'whatsapp_widget_enabled': '0',
        'whatsapp_widget_phone': '+2250160606183',
      });

      expect(contact.hasWhatsapp, isFalse);
    });

    test('disables WhatsApp when no phone is configured', () {
      final contact = SupportContactModel.fromSettings({
        'whatsapp_widget_enabled': '1',
      });

      expect(contact.hasWhatsapp, isFalse);
    });

    test('never throws and reports no channel on an empty/unreachable settings payload', () {
      final contact = SupportContactModel.fromSettings(const {});

      expect(contact.hasWhatsapp, isFalse);
      expect(contact.hasPhone, isFalse);
      expect(contact.hasEmail, isFalse);
    });
  });
}
