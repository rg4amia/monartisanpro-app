import '../../core/utils/json_readers.dart';

class SupplierCashoutStatsModel {
  final int walletMateriaux;
  final int availableBalance;
  final int pendingAmount;
  final int totalWithdrawn;
  final int totalRequests;

  const SupplierCashoutStatsModel({
    required this.walletMateriaux,
    required this.availableBalance,
    required this.pendingAmount,
    required this.totalWithdrawn,
    required this.totalRequests,
  });

  factory SupplierCashoutStatsModel.fromJson(Map<String, dynamic> json) {
    return SupplierCashoutStatsModel(
      walletMateriaux: readInt(json['wallet_materiaux']) ?? 0,
      availableBalance: readInt(json['available_balance']) ?? 0,
      pendingAmount: readInt(json['pending_amount']) ?? 0,
      totalWithdrawn: readInt(json['total_withdrawn']) ?? 0,
      totalRequests: readInt(json['total_requests']) ?? 0,
    );
  }

  factory SupplierCashoutStatsModel.empty() {
    return const SupplierCashoutStatsModel(
      walletMateriaux: 0,
      availableBalance: 0,
      pendingAmount: 0,
      totalWithdrawn: 0,
      totalRequests: 0,
    );
  }
}

class SupplierCashoutModel {
  final int id;
  final String reference;
  final int supplierId;
  final String beneficiaryName;
  final String beneficiaryPhone;
  final String? bankName;
  final String? bankAccountNumber;
  final int montantBrut;
  final double commissionRate;
  final int montantCommission;
  final int montantNet;
  final String statut;
  final String modeRetrait;
  final String? notes;
  final String? batchReference;
  final DateTime? processedAt;
  final DateTime? reconciledAt;
  final DateTime createdAt;

  const SupplierCashoutModel({
    required this.id,
    required this.reference,
    required this.supplierId,
    required this.beneficiaryName,
    required this.beneficiaryPhone,
    this.bankName,
    this.bankAccountNumber,
    required this.montantBrut,
    required this.commissionRate,
    required this.montantCommission,
    required this.montantNet,
    required this.statut,
    required this.modeRetrait,
    this.notes,
    this.batchReference,
    this.processedAt,
    this.reconciledAt,
    required this.createdAt,
  });

  factory SupplierCashoutModel.fromJson(Map<String, dynamic> json) {
    return SupplierCashoutModel(
      id: readInt(json['id']) ?? 0,
      reference: readString(json['reference']) ?? '',
      supplierId: readInt(json['supplier_id']) ?? 0,
      beneficiaryName: readString(json['beneficiary_name']) ?? '',
      beneficiaryPhone: readString(json['beneficiary_phone']) ?? '',
      bankName: readString(json['bank_name']),
      bankAccountNumber: readString(json['bank_account_number']),
      montantBrut: readInt(json['montant_brut']) ?? 0,
      commissionRate: readDouble(json['commission_rate']) ?? 0.025,
      montantCommission: readInt(json['montant_commission']) ?? 0,
      montantNet: readInt(json['montant_net']) ?? 0,
      statut: readString(json['statut']) ?? 'en_attente',
      modeRetrait: readString(json['mode_retrait']) ?? 'wave',
      notes: readString(json['notes']),
      batchReference: readString(json['batch_reference']),
      processedAt: json['processed_at'] != null
          ? DateTime.tryParse(json['processed_at'].toString())
          : null,
      reconciledAt: json['reconciled_at'] != null
          ? DateTime.tryParse(json['reconciled_at'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? (DateTime.tryParse(json['created_at'].toString()) ?? DateTime.now())
          : DateTime.now(),
    );
  }

  bool get isPending => statut == 'en_attente';
  bool get isApproved => statut == 'approuve';
  bool get isCompleted => statut == 'complete';
  bool get isRejected => statut == 'rejete';

  String get modeRetraitLabel {
    switch (modeRetrait) {
      case 'wave':
        return 'Wave CI';
      case 'orange_money':
        return 'Orange Money CI';
      case 'virement_bancaire':
        return 'Virement bancaire';
      case 'especes_guichet':
        return 'Espèces au guichet';
      default:
        return modeRetrait;
    }
  }

  String get statutLabel {
    switch (statut) {
      case 'en_attente':
        return 'En attente';
      case 'approuve':
        return 'Approuvé (J+1)';
      case 'complete':
        return 'Complété / Décaissé';
      case 'rejete':
        return 'Rejeté';
      default:
        return statut;
    }
  }
}
