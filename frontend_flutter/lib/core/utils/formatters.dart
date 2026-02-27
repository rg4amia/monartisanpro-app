import 'package:intl/intl.dart';

class Formatters {
  static final NumberFormat _fcfa = NumberFormat('#,###', 'fr_FR');
  static final DateFormat _date = DateFormat('dd/MM/yyyy', 'fr_FR');
  static final DateFormat _dateTime = DateFormat('dd/MM/yyyy HH:mm', 'fr_FR');
  static final DateFormat _short = DateFormat('dd MMM', 'fr_FR');

  static String fcfa(int amount) => '${_fcfa.format(amount)} FCFA';
  static String fcfaShort(int amount) => _fcfa.format(amount);
  static String date(String iso) => _date.format(DateTime.parse(iso));
  static String dateTime(String iso) => _dateTime.format(DateTime.parse(iso));
  static String shortDate(String iso) => _short.format(DateTime.parse(iso));

  static String phone(String raw) {
    if (raw.startsWith('+225') && raw.length >= 14) {
      final local = raw.substring(4);
      return '+225 ${local.substring(0, 2)} ${local.substring(2, 4)} ${local.substring(4, 6)} ${local.substring(6, 8)} ${local.substring(8)}';
    }
    return raw;
  }

  static String initial(String name) =>
      name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();

  static String missionStatus(String status) {
    const labels = {
      'en_attente': 'En attente',
      'financee': 'Financée',
      'en_cours': 'En cours',
      'terminee': 'Terminée',
      'litige': 'Litige',
    };
    return labels[status] ?? status;
  }

  static String kycStatus(String status) {
    const labels = {
      'en_attente': 'En attente',
      'actif': 'Actif',
      'rejete': 'Rejeté',
    };
    return labels[status] ?? status;
  }

  static String jcodeStatus(String status) {
    const labels = {
      'actif': 'Actif',
      'utilise': 'Utilisé',
      'expire': 'Expiré',
    };
    return labels[status] ?? status;
  }

  static String jalonStatus(String status) {
    const labels = {
      'en_attente': 'En attente',
      'soumis': 'Soumis',
      'valide': 'Validé',
      'paye': 'Payé',
    };
    return labels[status] ?? status;
  }

  static String role(String role) {
    const labels = {
      'client': 'Client',
      'artisan': 'Artisan',
      'fournisseur': 'Fournisseur',
      'referent': 'Référent',
      'admin': 'Administrateur',
    };
    return labels[role] ?? role;
  }

  static String distance(double metres) {
    if (metres < 1000) return '${metres.round()} m';
    return '${(metres / 1000).toStringAsFixed(1)} km';
  }

  static String timeAgo(String iso) {
    final dt = DateTime.parse(iso);
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return 'il y a ${diff.inMinutes} min';
    if (diff.inHours < 24) return 'il y a ${diff.inHours}h';
    if (diff.inDays < 7) return 'il y a ${diff.inDays}j';
    return _date.format(dt);
  }
}
