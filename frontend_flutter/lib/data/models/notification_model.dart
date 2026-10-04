import 'package:frontend_flutter/core/utils/json_readers.dart';

class NotificationModel {
  final int id;
  final String type; // payment | validation | alert | litige
  final String title;
  final String message;
  final bool isRead;
  final String createdAt;
  final Map<String, dynamic>? data;

  /// Rubrique du catalogue (`missions`, `finances`…). Nulle pour une
  /// notification antérieure au catalogue ou pour une campagne.
  final String? domain;

  const NotificationModel({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.isRead,
    required this.createdAt,
    this.data,
    this.domain,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) =>
      NotificationModel(
        id: readInt(json['id']) ?? 0,
        type: readString(json['type']) ?? 'alert',
        title: readString(json['title']) ?? '',
        message: readString(json['message']) ?? '',
        isRead: readBool(json['isRead']) ?? readBool(json['read']) ?? false,
        createdAt: readString(json['createdAt']) ??
            readString(json['created_at']) ??
            DateTime.now().toIso8601String(),
        data: readMap(json['data']),
        domain: readString(json['domain']),
      );

  NotificationModel asRead() => NotificationModel(
        id: id,
        type: type,
        title: title,
        message: message,
        isRead: true,
        createdAt: createdAt,
        data: data,
        domain: domain,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'title': title,
        'message': message,
        'isRead': isRead,
        'createdAt': createdAt,
        'data': data,
        'domain': domain,
      };
}

/// Rubrique présente dans les notifications de l'utilisateur, avec son
/// nombre de non lues.
class NotificationDomain {
  const NotificationDomain({
    required this.key,
    required this.label,
    required this.unread,
  });

  final String key;
  final String label;
  final int unread;

  factory NotificationDomain.fromJson(Map<String, dynamic> json) =>
      NotificationDomain(
        key: readString(json['key']) ?? '',
        label: readString(json['label']) ?? '',
        unread: readInt(json['unread']) ?? 0,
      );

  NotificationDomain withUnread(int value) =>
      NotificationDomain(key: key, label: label, unread: value < 0 ? 0 : value);
}

/// Une page de notifications. Le nombre de non lues et les rubriques portent
/// sur toutes les notifications de l'utilisateur, pas sur la page affichée.
class NotificationPage {
  const NotificationPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.unread,
    required this.domains,
  });

  final List<NotificationModel> items;
  final int currentPage;
  final int lastPage;
  final int unread;
  final List<NotificationDomain> domains;

  bool get hasMore => currentPage < lastPage;

  /// Lit l'enveloppe `{data: [...], meta: {...}}`. Une réponse sans liste lève
  /// une `FormatException` : une panne ne passe jamais pour une liste vide.
  factory NotificationPage.fromResponse(dynamic body) {
    final items = readDataList(body).map(NotificationModel.fromJson).toList();
    final meta = readMap(readMap(body)?['meta']) ?? const {};

    return NotificationPage(
      items: items,
      currentPage: readInt(meta['current_page']) ?? 1,
      lastPage: readInt(meta['last_page']) ?? 1,
      unread: readInt(meta['unread']) ?? items.where((n) => !n.isRead).length,
      domains: readMapList(meta['domains'])
          .map(NotificationDomain.fromJson)
          .where((d) => d.key.isNotEmpty)
          .toList(),
    );
  }
}

/// Réglage d'une rubrique : ce que l'utilisateur reçoit et ce qu'il peut couper.
class NotificationDomainPreference {
  const NotificationDomainPreference({
    required this.key,
    required this.label,
    required this.push,
    required this.sms,
    required this.pushEditable,
    required this.smsEditable,
    required this.essential,
  });

  final String key;
  final String label;
  final bool push;
  final bool sms;
  final bool pushEditable;
  final bool smsEditable;

  /// La rubrique contient des messages essentiels, toujours envoyés.
  final bool essential;

  factory NotificationDomainPreference.fromJson(Map<String, dynamic> json) =>
      NotificationDomainPreference(
        key: readString(json['key']) ?? '',
        label: readString(json['label']) ?? '',
        push: readBool(json['push']) ?? true,
        sms: readBool(json['sms']) ?? false,
        pushEditable: readBool(json['push_editable']) ?? false,
        smsEditable: readBool(json['sms_editable']) ?? false,
        essential: readBool(json['essential']) ?? false,
      );

  NotificationDomainPreference copyWith({bool? push, bool? sms}) =>
      NotificationDomainPreference(
        key: key,
        label: label,
        push: push ?? this.push,
        sms: sms ?? this.sms,
        pushEditable: pushEditable,
        smsEditable: smsEditable,
        essential: essential,
      );
}

class NotificationPreferences {
  const NotificationPreferences({
    required this.promotionalPush,
    required this.domains,
  });

  /// Accord aux offres et nouveautés : jamais supposé, `false` par défaut.
  final bool promotionalPush;
  final List<NotificationDomainPreference> domains;

  /// Lit `{data: {promotional_push, domains: [...]}}`. Une réponse sans la
  /// liste des rubriques lève une `FormatException`.
  factory NotificationPreferences.fromResponse(dynamic body) {
    final data = readMap(readMap(body)?['data']);
    if (data == null || readList(data['domains']) == null) {
      throw const FormatException('Préférences de notification illisibles.');
    }

    return NotificationPreferences(
      promotionalPush: readBool(data['promotional_push']) ?? false,
      domains: readMapList(data['domains'])
          .map(NotificationDomainPreference.fromJson)
          .where((d) => d.key.isNotEmpty)
          .toList(),
    );
  }
}
