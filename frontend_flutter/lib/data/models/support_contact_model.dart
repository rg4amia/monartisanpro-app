/// Coordonnées de contact du support ProsArtisan, dérivées des paramètres
/// publics (`/vitrine/settings`) partagés avec le site vitrine et le
/// backoffice (onglets Paramètres Vitrine / WhatsApp).
class SupportContactModel {
  final bool whatsappEnabled;
  final String whatsappPhone;
  final String whatsappMessage;
  final String contactPhone;
  final String contactEmail;

  const SupportContactModel({
    required this.whatsappEnabled,
    required this.whatsappPhone,
    required this.whatsappMessage,
    required this.contactPhone,
    required this.contactEmail,
  });

  factory SupportContactModel.fromSettings(Map<String, dynamic> settings) {
    String read(String key, [String fallback = '']) {
      final value = settings[key];
      if (value == null) return fallback;
      final text = value.toString().trim();
      return text.isEmpty ? fallback : text;
    }

    return SupportContactModel(
      whatsappEnabled: read('whatsapp_widget_enabled', '1') != '0',
      whatsappPhone: read('whatsapp_widget_phone'),
      whatsappMessage: read(
        'whatsapp_widget_message',
        "Bonjour ProsArtisan, j'ai besoin d'assistance.",
      ),
      contactPhone: read('contact_phone'),
      contactEmail: read('contact_email'),
    );
  }

  bool get hasWhatsapp => whatsappEnabled && whatsappPhone.isNotEmpty;

  bool get hasPhone => contactPhone.isNotEmpty;

  bool get hasEmail => contactEmail.isNotEmpty;
}
