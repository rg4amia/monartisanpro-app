class TransactionModel {
  final int id;
  final String type;
  final int montant;
  final String walletSource;
  final String walletDest;
  final String provider;
  final String statut;
  final String? referenceExterne;
  final int? missionId;
  final String? missionDescription;
  final String? clientName;
  final String createdAt;

  const TransactionModel({
    required this.id,
    required this.type,
    required this.montant,
    required this.walletSource,
    required this.walletDest,
    required this.provider,
    required this.statut,
    required this.createdAt,
    this.referenceExterne,
    this.missionId,
    this.missionDescription,
    this.clientName,
  });

  factory TransactionModel.fromJson(Map<String, dynamic> json) =>
      TransactionModel(
        id: _parseInt(json['id']),
        type: (json['type'] ?? '').toString(),
        montant: _parseInt(json['montant']),
        walletSource:
            (json['walletSource'] ?? json['wallet_source'] ?? '').toString(),
        walletDest:
            (json['walletDest'] ?? json['wallet_dest'] ?? '').toString(),
        provider: (json['provider'] ?? '').toString(),
        statut: (json['statut'] ?? json['status'] ?? '').toString(),
        createdAt: (json['createdAt'] ??
                json['created_at'] ??
                DateTime.now().toIso8601String())
            .toString(),
        referenceExterne:
            (json['referenceExterne'] ?? json['reference_externe']) as String?,
        missionId: json['missionId'] != null
            ? _parseInt(json['missionId'])
            : (json['mission_id'] != null
                ? _parseInt(json['mission_id'])
                : null),
        missionDescription: (json['missionDescription'] ??
            json['mission_description']) as String?,
        clientName: (json['clientName'] ?? json['client_name']) as String?,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'montant': montant,
        'walletSource': walletSource,
        'walletDest': walletDest,
        'provider': provider,
        'statut': statut,
        'createdAt': createdAt,
        'referenceExterne': referenceExterne,
        'missionId': missionId,
        'missionDescription': missionDescription,
        'clientName': clientName,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }
}

class WalletBalance {
  final int walletMateriaux;
  final int walletMo;

  const WalletBalance({required this.walletMateriaux, required this.walletMo});

  factory WalletBalance.fromJson(Map<String, dynamic> json) => WalletBalance(
        walletMateriaux: TransactionModel._parseInt(
          json['walletMateriaux'] ?? json['wallet_materiaux'],
        ),
        walletMo:
            TransactionModel._parseInt(json['walletMo'] ?? json['wallet_mo']),
      );
}
