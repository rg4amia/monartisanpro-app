import '../../core/utils/json_readers.dart';

/// Action de l'historique d'un versement Mobile Money (tentative, échec,
/// relance, versement manuel…), consignée côté serveur en ajout seul.
class PayoutEventModel {
  final int id;
  final String action;
  final String actionLabel;
  final String? message;
  final String? actor;
  final DateTime? createdAt;

  const PayoutEventModel({
    required this.id,
    required this.action,
    required this.actionLabel,
    this.message,
    this.actor,
    this.createdAt,
  });

  factory PayoutEventModel.fromJson(Map<String, dynamic> json) {
    final action = readString(json['action']) ?? '';

    return PayoutEventModel(
      id: readInt(json['id']) ?? 0,
      action: action,
      actionLabel: readString(json['action_label']) ?? action,
      message: readString(json['message']),
      actor: readString(json['actor']),
      createdAt: DateTime.tryParse(readString(json['created_at']) ?? ''),
    );
  }
}

/// Versement Mobile Money sortant vers l'utilisateur (paiement d'étape,
/// règlement de litige, retrait livreur). Un versement échoué n'a pas débité
/// le portefeuille : il reste relançable.
class PayoutModel {
  final int id;
  final String reference;
  final String contextLabel;
  final int montant;
  final String provider;
  final String? phone;
  final String statut;
  final String statutLabel;
  final int attempts;
  final String? lastError;
  final DateTime? nextRetryAt;
  final bool canRetry;
  final List<PayoutEventModel> events;

  const PayoutModel({
    required this.id,
    required this.reference,
    required this.contextLabel,
    required this.montant,
    required this.provider,
    required this.statut,
    required this.statutLabel,
    required this.attempts,
    required this.canRetry,
    this.phone,
    this.lastError,
    this.nextRetryAt,
    this.events = const [],
  });

  bool get isPaid => statut == 'verse';
  bool get isFailed => statut == 'echoue';
  bool get isPending => statut == 'echoue' || statut == 'en_cours';

  factory PayoutModel.fromJson(Map<String, dynamic> json) {
    final statut = readString(json['statut']) ?? 'en_cours';

    return PayoutModel(
      id: readInt(json['id']) ?? 0,
      reference: readString(json['reference']) ?? '',
      contextLabel: readString(json['context_label']) ?? 'Versement',
      // Montant réellement viré (net des frais éventuels de retrait).
      montant:
          readInt(json['montant_transfere']) ?? readInt(json['montant']) ?? 0,
      provider: readString(json['provider']) ?? 'wave',
      phone: readString(json['phone']),
      statut: statut,
      statutLabel: readString(json['statut_label']) ?? statut,
      attempts: readInt(json['attempts']) ?? 0,
      lastError: readString(json['last_error']),
      nextRetryAt: DateTime.tryParse(readString(json['next_retry_at']) ?? ''),
      canRetry: readBool(json['can_retry']) ?? false,
      events: readMapList(json['events'])
          .map(PayoutEventModel.fromJson)
          .toList(growable: false),
    );
  }
}

/// Demande de retrait des gains d'un livreur.
class DriverCashoutModel {
  final int id;
  final String reference;
  final int montantBrut;
  final int montantCommission;
  final int montantNet;
  final String statut;
  final String statutLabel;
  final String modeRetrait;
  final String? notes;
  final PayoutModel? payout;
  final DateTime? createdAt;

  const DriverCashoutModel({
    required this.id,
    required this.reference,
    required this.montantBrut,
    required this.montantCommission,
    required this.montantNet,
    required this.statut,
    required this.statutLabel,
    required this.modeRetrait,
    this.notes,
    this.payout,
    this.createdAt,
  });

  factory DriverCashoutModel.fromJson(Map<String, dynamic> json) {
    final statut = readString(json['statut']) ?? 'en_attente';
    final payout = readMap(json['payout']);

    return DriverCashoutModel(
      id: readInt(json['id']) ?? 0,
      reference: readString(json['reference']) ?? '',
      montantBrut: readInt(json['montant_brut']) ?? 0,
      montantCommission: readInt(json['montant_commission']) ?? 0,
      montantNet: readInt(json['montant_net']) ?? 0,
      statut: statut,
      statutLabel: readString(json['statut_label']) ?? statut,
      modeRetrait: readString(json['mode_retrait']) ?? 'wave',
      notes: readString(json['notes']),
      payout: payout != null ? PayoutModel.fromJson(payout) : null,
      createdAt: DateTime.tryParse(readString(json['created_at']) ?? ''),
    );
  }
}

/// Solde retirable et indicateurs du livreur.
class DriverCashoutStats {
  final int walletMo;
  final int availableBalance;
  final int pendingAmount;
  final int totalWithdrawn;
  final double commissionRate;
  final int minimumAmount;

  const DriverCashoutStats({
    this.walletMo = 0,
    this.availableBalance = 0,
    this.pendingAmount = 0,
    this.totalWithdrawn = 0,
    this.commissionRate = 0,
    this.minimumAmount = 500,
  });

  factory DriverCashoutStats.fromJson(Map<String, dynamic> json) =>
      DriverCashoutStats(
        walletMo: readInt(json['wallet_mo']) ?? 0,
        availableBalance: readInt(json['available_balance']) ?? 0,
        pendingAmount: readInt(json['pending_amount']) ?? 0,
        totalWithdrawn: readInt(json['total_withdrawn']) ?? 0,
        commissionRate: readDouble(json['commission_rate']) ?? 0,
        minimumAmount: readInt(json['minimum_amount']) ?? 500,
      );
}
