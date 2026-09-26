import '../../core/utils/json_readers.dart';

/// Montant d'une course livreur « à la Yango » (Chantier 10) : estimé à
/// l'acceptation, révélé à la livraison (tarif + bonus d'attente), puis réglé
/// par le client.
class DeliveryFare {
  final int base;
  final int waitingBonus;
  final int waitingMinutes;
  final int total;
  final int due;

  /// `estimation`, `a_payer` ou `paye`.
  final String status;
  final String statusLabel;

  const DeliveryFare({
    required this.base,
    required this.waitingBonus,
    required this.waitingMinutes,
    required this.total,
    required this.due,
    required this.status,
    required this.statusLabel,
  });

  bool get isEstimate => status == 'estimation';
  bool get isAwaitingPayment => status == 'a_payer';
  bool get isPaid => status == 'paye';

  /// `null` si la charge utile ne décrit pas de course (retrait magasin,
  /// réponse antérieure au Chantier 10).
  static DeliveryFare? tryParse(dynamic value) {
    final json = readMap(value);
    if (json == null) return null;

    final base = readInt(json['base']) ?? 0;
    final waitingBonus = readInt(json['waiting_bonus']) ?? 0;
    final status = readString(json['status']) ?? 'estimation';

    return DeliveryFare(
      base: base,
      waitingBonus: waitingBonus,
      waitingMinutes: readInt(json['waiting_minutes']) ?? 0,
      total: readInt(json['total']) ?? base + waitingBonus,
      due: readInt(json['due']) ?? 0,
      status: status,
      statusLabel: readString(json['status_label']) ??
          switch (status) {
            'a_payer' => 'Course à régler',
            'paye' => 'Course réglée',
            _ => 'Estimation',
          },
    );
  }
}
