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
      walletMateriaux: (json['wallet_materiaux'] as num?)?.toInt() ?? 0,
      availableBalance: (json['available_balance'] as num?)?.toInt() ?? 0,
      pendingAmount: (json['pending_amount'] as num?)?.toInt() ?? 0,
      totalWithdrawn: (json['total_withdrawn'] as num?)?.toInt() ?? 0,
      totalRequests: (json['total_requests'] as num?)?.toInt() ?? 0,
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
      id: (json['id'] as num?)?.toInt() ?? 0,
      reference: (json['reference'] as String?) ?? '',
      supplierId: (json['supplier_id'] as num?)?.toInt() ?? 0,
      beneficiaryName: (json['beneficiary_name'] as String?) ?? '',
      beneficiaryPhone: (json['beneficiary_phone'] as String?) ?? '',
      bankName: json['bank_name'] as String?,
      bankAccountNumber: json['bank_account_number'] as String?,
      montantBrut: (json['montant_brut'] as num?)?.toInt() ?? 0,
      commissionRate: (json['commission_rate'] as num?)?.toDouble() ?? 0.025,
      montantCommission: (json['montant_commission'] as num?)?.toInt() ?? 0,
      montantNet: (json['montant_net'] as num?)?.toInt() ?? 0,
      statut: (json['statut'] as String?) ?? 'en_attente',
      modeRetrait: (json['mode_retrait'] as String?) ?? 'wave',
      notes: json['notes'] as String?,
      batchReference: json['batch_reference'] as String?,
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
