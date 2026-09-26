import '../../core/utils/json_readers.dart';

/// Verdicts proposés au juré, tenus par le serveur
/// (`LitigeService::submitJuryVote`).
class JuryVerdict {
  static const String conforme = 'CONFORME';
  static const String nonConforme = 'NON_CONFORME';
  static const String partage = 'RESPONSABILITE_PARTAGEE';

  static const List<String> all = [conforme, nonConforme, partage];

  static String label(String? verdict) {
    switch (verdict) {
      case conforme:
        return 'Travaux conformes';
      case nonConforme:
        return 'Travaux non conformes';
      case partage:
        return 'Responsabilité partagée';
      default:
        return 'Non voté';
    }
  }
}

/// Statut d'une assignation de juré, libellé en français (Règle d'or 27).
String juryReviewStatusLabel(String status) {
  switch (status) {
    case 'assigned':
      return 'À instruire';
    case 'voted':
      return 'Avis rendu';
    case 'expired':
      return 'Délai dépassé';
    default:
      return status;
  }
}

/// Dossier d'arbitrage assigné au juré connecté (Chantier 12). Anonymisé
/// par le serveur : aucun nom ni numéro des parties.
class JuryDossierSummary {
  final int reviewId;
  final int litigeId;
  final String status;
  final int compensation;
  final DateTime? expiresAt;
  final DateTime? votedAt;
  final String? verdict;
  final String interventionType;
  final int montantMission;
  final String? motif;
  final int preuvesCount;

  const JuryDossierSummary({
    required this.reviewId,
    required this.litigeId,
    required this.status,
    required this.compensation,
    required this.interventionType,
    required this.montantMission,
    required this.preuvesCount,
    this.expiresAt,
    this.votedAt,
    this.verdict,
    this.motif,
  });

  bool get isOpen => status == 'assigned';

  factory JuryDossierSummary.fromJson(Map<String, dynamic> json) {
    final dossier = readMap(json['dossier']) ?? const <String, dynamic>{};

    return JuryDossierSummary(
      reviewId: readInt(json['review_id']) ?? 0,
      litigeId: readInt(json['litige_id']) ?? 0,
      status: readString(json['status']) ?? 'assigned',
      compensation: readInt(json['compensation']) ?? 0,
      expiresAt: DateTime.tryParse(readString(json['expires_at']) ?? ''),
      votedAt: DateTime.tryParse(readString(json['voted_at']) ?? ''),
      verdict: readString(json['verdict']),
      interventionType:
          readString(dossier['intervention_type']) ?? 'Non spécifié',
      montantMission: readInt(dossier['montant_mission']) ?? 0,
      motif: readString(dossier['motif']),
      preuvesCount: readInt(dossier['preuves_count']) ?? 0,
    );
  }
}

/// Pièce du dossier, présentée avec son empreinte d'intégrité SHA-256.
class JuryEvidence {
  final int id;
  final String partie;
  final String? mediaUrl;
  final String? description;
  final bool isCertified;
  final bool tampered;

  const JuryEvidence({
    required this.id,
    required this.partie,
    required this.isCertified,
    required this.tampered,
    this.mediaUrl,
    this.description,
  });

  String get partieLabel => partie == 'artisan' ? 'Artisan' : 'Client';

  factory JuryEvidence.fromJson(Map<String, dynamic> json) => JuryEvidence(
        id: readInt(json['id']) ?? 0,
        partie: readString(json['partie']) ?? 'client',
        mediaUrl: readString(json['media_url']),
        description: readString(json['description']),
        isCertified: readBool(json['is_certified']) ?? false,
        tampered: readBool(json['tampered']) ?? false,
      );
}

class JuryJalonSummary {
  final int ordre;
  final String? description;
  final int montant;
  final String? statut;

  const JuryJalonSummary({
    required this.ordre,
    required this.montant,
    this.description,
    this.statut,
  });

  factory JuryJalonSummary.fromJson(Map<String, dynamic> json) =>
      JuryJalonSummary(
        ordre: readInt(json['ordre']) ?? 0,
        description: readString(json['description']),
        montant: readInt(json['montant']) ?? 0,
        statut: readString(json['statut']),
      );
}

/// Dossier anonymisé complet, examiné par le juré avant son vote.
class JuryDossierDetail {
  final int reviewId;
  final String status;
  final int compensation;
  final DateTime? expiresAt;
  final String? verdict;
  final int? splitArtisanPercentage;
  final String? technicalComment;
  final int litigeId;
  final String interventionType;
  final int montantMission;
  final String? motif;
  final String? description;
  final String? ouvertPar;
  final List<JuryEvidence> preuves;
  final List<JuryJalonSummary> jalons;

  const JuryDossierDetail({
    required this.reviewId,
    required this.status,
    required this.compensation,
    required this.litigeId,
    required this.interventionType,
    required this.montantMission,
    this.expiresAt,
    this.verdict,
    this.splitArtisanPercentage,
    this.technicalComment,
    this.motif,
    this.description,
    this.ouvertPar,
    this.preuves = const [],
    this.jalons = const [],
  });

  bool get canVote => status == 'assigned' && verdict == null;

  factory JuryDossierDetail.fromJson(Map<String, dynamic> json) {
    final review = readMap(json['review']) ?? const <String, dynamic>{};
    final litige = readMap(json['litige']) ?? const <String, dynamic>{};

    return JuryDossierDetail(
      reviewId: readInt(review['id']) ?? 0,
      status: readString(review['status']) ?? 'assigned',
      compensation: readInt(review['compensation']) ?? 0,
      expiresAt: DateTime.tryParse(readString(review['expires_at']) ?? ''),
      verdict: readString(review['verdict']),
      splitArtisanPercentage: readInt(review['split_artisan_percentage']),
      technicalComment: readString(review['technical_comment']),
      litigeId: readInt(litige['id']) ?? 0,
      interventionType: readString(litige['intervention_type']) ?? 'Standard',
      montantMission: readInt(litige['montant_mission']) ?? 0,
      motif: readString(litige['motif']),
      description: readString(litige['description']),
      ouvertPar: readString(litige['ouvert_par']),
      preuves: readMapList(litige['preuves'])
          .map(JuryEvidence.fromJson)
          .toList(growable: false),
      jalons: readMapList(litige['jalons_summary'])
          .map(JuryJalonSummary.fromJson)
          .toList(growable: false),
    );
  }
}
