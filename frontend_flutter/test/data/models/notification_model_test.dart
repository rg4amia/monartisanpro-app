import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/notification_model.dart';

void main() {
  group('NotificationModel.fromJson (Règle d\'or 28)', () {
    test('accepte une charge « data » en Map<dynamic, dynamic> (cache Hive)',
        () {
      final Map<dynamic, dynamic> data = {'mission_id': 12};

      final notification = NotificationModel.fromJson({
        'id': 1,
        'type': 'payment',
        'title': 'Paiement reçu',
        'message': 'Votre acompte a été crédité.',
        'isRead': 0,
        'created_at': '2026-09-24T10:00:00Z',
        'data': data,
      });

      expect(notification.data, {'mission_id': 12});
      expect(notification.isRead, isFalse);
    });

    test('ignore une charge « data » vide sérialisée en tableau par PHP', () {
      final notification = NotificationModel.fromJson({'id': 2, 'data': []});

      expect(notification.data, isNull);
      expect(notification.type, 'alert');
    });
  });
}
