class VirementInstructionsModel {
  final String bankName;
  final String accountName;
  final String iban;
  final String reference;

  const VirementInstructionsModel({
    required this.bankName,
    required this.accountName,
    required this.iban,
    required this.reference,
  });

  factory VirementInstructionsModel.fromJson(Map<String, dynamic> json) =>
      VirementInstructionsModel(
        bankName: (json['bank_name'] ?? json['bankName'] ?? '').toString(),
        accountName: (json['account_name'] ?? json['accountName'] ?? '').toString(),
        iban: (json['iban'] ?? '').toString(),
        reference: (json['reference'] ?? '').toString(),
      );
}

class PaymentInitiationModel {
  final int transactionId;
  final int? devisId;
  final String provider;
  final String? paymentUrl;
  final String? waveLaunchUrl;
  final String? orderId;
  final VirementInstructionsModel? virementInstructions;

  const PaymentInitiationModel({
    required this.transactionId,
    required this.provider,
    this.devisId,
    this.paymentUrl,
    this.waveLaunchUrl,
    this.orderId,
    this.virementInstructions,
  });

  String? get launchUrl => waveLaunchUrl ?? paymentUrl;

  factory PaymentInitiationModel.fromJson(Map<String, dynamic> json) =>
      PaymentInitiationModel(
        transactionId:
            _parseInt(json['transaction_id'] ?? json['transactionId']),
        devisId: _parseNullableInt(json['devis_id'] ?? json['devisId']),
        provider: (json['provider'] ?? '').toString(),
        paymentUrl:
            json['payment_url']?.toString() ?? json['paymentUrl']?.toString(),
        waveLaunchUrl: json['wave_launch_url']?.toString() ??
            json['waveLaunchUrl']?.toString(),
        orderId: json['order_id']?.toString() ?? json['orderId']?.toString(),
        virementInstructions: json['virement_instructions'] != null
            ? VirementInstructionsModel.fromJson(
                json['virement_instructions'] as Map<String, dynamic>)
            : json['virementInstructions'] != null
                ? VirementInstructionsModel.fromJson(
                    json['virementInstructions'] as Map<String, dynamic>)
                : null,
      );
}

class PaymentStatusModel {
  final int transactionId;
  final String status;
  final int montant;
  final String provider;
  final int? missionId;
  final int? devisId;
  final String? paidAt;
  final String? failedAt;

  const PaymentStatusModel({
    required this.transactionId,
    required this.status,
    required this.montant,
    required this.provider,
    this.missionId,
    this.devisId,
    this.paidAt,
    this.failedAt,
  });

  bool get isConfirmed => status == 'confirme';
  bool get isFailed => status == 'echoue';
  bool get isPending => status == 'en_attente';

  factory PaymentStatusModel.fromJson(Map<String, dynamic> json) =>
      PaymentStatusModel(
        transactionId:
            _parseInt(json['transaction_id'] ?? json['transactionId']),
        status: (json['status'] ?? '').toString(),
        montant: _parseInt(json['montant']),
        provider: (json['provider'] ?? '').toString(),
        missionId: _parseNullableInt(json['mission_id'] ?? json['missionId']),
        devisId: _parseNullableInt(json['devis_id'] ?? json['devisId']),
        paidAt: json['paid_at']?.toString() ?? json['paidAt']?.toString(),
        failedAt: json['failed_at']?.toString() ?? json['failedAt']?.toString(),
      );
}

int _parseInt(dynamic value) {
  if (value == null) return 0;
  if (value is int) return value;
  if (value is double) return value.toInt();
  return int.tryParse(value.toString()) ?? 0;
}

int? _parseNullableInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is double) return value.toInt();
  return int.tryParse(value.toString());
}
