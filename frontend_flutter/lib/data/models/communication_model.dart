import 'package:frontend_flutter/core/utils/json_readers.dart';

class CommunicationModel {
  final int id;
  final String type; // annonce | le_saviez_vous | audio | video
  final String titre;
  final String contenu;
  final List<String> cibles;
  final String statut;
  final String? publieAt;
  final String? clotureAt;
  final String createdAt;

  /// URL de lecture, calculée par l'API : fichier téléversé pour une note
  /// vocale, lien de la plateforme d'hébergement pour une vidéo.
  final String? mediaUrl;

  final String? mediaMime;

  /// Octets et secondes, affichés **avant** la lecture : sur un forfait
  /// mobile ivoirien, l'utilisateur doit savoir ce qu'il engage.
  final int? mediaSize;
  final int? mediaDuration;

  const CommunicationModel({
    required this.id,
    required this.type,
    required this.titre,
    required this.contenu,
    required this.cibles,
    required this.statut,
    this.publieAt,
    this.clotureAt,
    required this.createdAt,
    this.mediaUrl,
    this.mediaMime,
    this.mediaSize,
    this.mediaDuration,
  });

  bool get isAudio => type == 'audio';

  bool get isVideo => type == 'video';

  bool get hasMedia => (mediaUrl ?? '').isNotEmpty;

  /// Durée au format `m:ss`, ou `null` si l'API ne l'a pas renseignée.
  String? get formattedDuration {
    final seconds = mediaDuration;
    if (seconds == null || seconds <= 0) return null;

    final minutes = seconds ~/ 60;
    final rest = (seconds % 60).toString().padLeft(2, '0');

    return '$minutes:$rest';
  }

  /// Poids du média, en Ko sous le mégaoctet.
  ///
  /// Une note vocale pèse quelques centaines de kilooctets : l'afficher
  /// « 0.5 Mo » plutôt que « 480 Ko » perd la précision utile à qui compte
  /// son forfait.
  String? get formattedSize {
    final bytes = mediaSize;
    if (bytes == null || bytes <= 0) return null;

    final mo = bytes / (1024 * 1024);

    return mo >= 1
        ? '${mo.toStringAsFixed(1)} Mo'
        : '${(bytes / 1024).round()} Ko';
  }

  factory CommunicationModel.fromJson(Map<String, dynamic> json) {
    var ciblesList = <String>[];
    if (json['cibles_json'] is List) {
      ciblesList = List<String>.from(json['cibles_json']);
    } else if (json['cibles'] is List) {
      ciblesList = List<String>.from(json['cibles']);
    }

    return CommunicationModel(
      id: readInt(json['id']) ?? 0,
      type: readString(json['type']) ?? 'annonce',
      titre: readString(json['titre']) ?? readString(json['title']) ?? '',
      contenu: readString(json['contenu']) ??
          readString(json['content']) ??
          readString(json['message']) ??
          '',
      cibles: ciblesList,
      statut: readString(json['statut']) ??
          readString(json['status']) ??
          'brouillon',
      publieAt: readString(json['publie_at']),
      clotureAt: readString(json['cloture_at']),
      createdAt:
          readString(json['created_at']) ?? DateTime.now().toIso8601String(),
      // Lecture tolérante : ces clés sont absentes des réponses d'un backend
      // antérieur, et d'un cache Hive écrit avant la mise à jour.
      mediaUrl: json['media_url']?.toString(),
      mediaMime: json['media_mime']?.toString(),
      mediaSize: _asInt(json['media_size']),
      mediaDuration: _asInt(json['media_duration']),
    );
  }

  static int? _asInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();

    return int.tryParse(value.toString());
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'titre': titre,
        'contenu': contenu,
        'cibles_json': cibles,
        'statut': statut,
        'publie_at': publieAt,
        'cloture_at': clotureAt,
        'created_at': createdAt,
        'media_url': mediaUrl,
        'media_mime': mediaMime,
        'media_size': mediaSize,
        'media_duration': mediaDuration,
      };
}
