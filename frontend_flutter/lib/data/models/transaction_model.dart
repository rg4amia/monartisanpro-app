class TransactionModel {
  final int id;
  final String type;
  final int montant;
  final String walletSource;
  final String walletDest;
  final String provider;
  final String statut;
  final String? referenceExterne;
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
  });

  factory TransactionModel.fromJson(Map<String, dynamic> json) =>
      TransactionModel(
        id: json['id'] as int,
        type: json['type'] as String,
        montant: json['montant'] as int,
        walletSource: json['walletSource'] as String,
        walletDest: json['walletDest'] as String,
        provider: json['provider'] as String,
        statut: json['statut'] as String,
        createdAt: json['createdAt'] as String,
        referenceExterne: json['referenceExterne'] as String?,
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
      };
}

class WalletBalance {
  final int walletMateriaux;
  final int walletMo;

  const WalletBalance({
    required this.walletMateriaux,
    required this.walletMo,
  });

  factory WalletBalance.fromJson(Map<String, dynamic> json) => WalletBalance(
        walletMateriaux: json['walletMateriaux'] as int,
        walletMo: json['walletMo'] as int,
      );
}
