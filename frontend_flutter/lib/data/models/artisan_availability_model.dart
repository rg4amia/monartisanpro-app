import '../../core/utils/json_readers.dart';

/// Statuts de disponibilité de l'annuaire (Chantier 15).
const kAvailabilityStatusLabels = <String, String>{
  'disponible': 'Disponible',
  'occupe': 'Occupé',
  'conge': 'En congé',
};

const kAvailabilityDayLabels = <int, String>{
  1: 'Lundi',
  2: 'Mardi',
  3: 'Mercredi',
  4: 'Jeudi',
  5: 'Vendredi',
  6: 'Samedi',
  7: 'Dimanche',
};

/// Plage horaire habituelle : jour (1 = lundi) et heures `HH:MM`.
class AvailabilitySlot {
  const AvailabilitySlot({
    required this.day,
    required this.start,
    required this.end,
  });

  final int day;
  final String start;
  final String end;

  static AvailabilitySlot? fromJson(Map<String, dynamic> json) {
    final day = readInt(json['day']);
    final start = readString(json['start']);
    final end = readString(json['end']);
    if (day == null || start == null || end == null) return null;
    return AvailabilitySlot(day: day, start: start, end: end);
  }

  Map<String, dynamic> toJson() => {'day': day, 'start': start, 'end': end};

  AvailabilitySlot copyWith({int? day, String? start, String? end}) =>
      AvailabilitySlot(
        day: day ?? this.day,
        start: start ?? this.start,
        end: end ?? this.end,
      );
}

/// Une version de la disponibilité : publiée, en attente ou refusée.
class ArtisanAvailability {
  const ArtisanAvailability({
    required this.status,
    required this.label,
    required this.reviewStatus,
    this.untilDate,
    this.slots = const [],
    this.scheduleSummary,
    this.nightWork = false,
    this.rejectionReason,
  });

  final String status;

  /// Libellé prêt à afficher (« Occupé jusqu'au 15/10/2026 »).
  final String label;
  final String reviewStatus;
  final DateTime? untilDate;
  final List<AvailabilitySlot> slots;
  final String? scheduleSummary;
  final bool nightWork;
  final String? rejectionReason;

  /// Lecture défensive, un bloc par champ (Règle d'or 28).
  factory ArtisanAvailability.fromJson(Map<String, dynamic> json) {
    final status = readString(json['status']) ?? 'disponible';
    DateTime? until;
    final rawUntil = readString(json['until_date']);
    if (rawUntil != null) until = DateTime.tryParse(rawUntil);

    return ArtisanAvailability(
      status: status,
      label: readString(json['effective_label']) ??
          kAvailabilityStatusLabels[status] ??
          status,
      reviewStatus: readString(json['review_status']) ?? 'en_attente',
      untilDate: until,
      slots: readMapList(json['schedule'])
          .map(AvailabilitySlot.fromJson)
          .whereType<AvailabilitySlot>()
          .toList(growable: false),
      scheduleSummary: readString(json['schedule_summary']),
      nightWork: readBool(json['night_work']) ?? false,
      rejectionReason: readString(json['rejection_reason']),
    );
  }
}

/// Ce que l'artisan voit : la version publiée, sa déclaration en attente et
/// le dernier refus (avec son motif) s'il est postérieur à la publication.
class AvailabilityOverview {
  const AvailabilityOverview({this.published, this.pending, this.lastRejected});

  final ArtisanAvailability? published;
  final ArtisanAvailability? pending;
  final ArtisanAvailability? lastRejected;

  factory AvailabilityOverview.fromJson(Map<String, dynamic>? json) {
    ArtisanAvailability? read(String key) {
      final map = readMap(json?[key]);
      return map == null ? null : ArtisanAvailability.fromJson(map);
    }

    return AvailabilityOverview(
      published: read('published'),
      pending: read('pending'),
      lastRejected: read('last_rejected'),
    );
  }
}
