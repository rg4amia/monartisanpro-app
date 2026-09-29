import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/services/notification_service.dart';

/// Chantier 14, lot D : écran ouvert au toucher d'une campagne du backoffice.
void main() {
  test("une campagne vers l'accueil ou une communication ouvre l'accueil", () {
    expect(
      NotificationService.campaignOpensHome(
        {'type': 'campaign', 'screen': 'home'},
      ),
      isTrue,
    );
    expect(
      NotificationService.campaignOpensHome({
        'type': 'campaign',
        'screen': 'communication',
        'communication_id': 4,
      }),
      isTrue,
    );
  });

  test('sans écran précisé, une campagne ouvre la liste des notifications', () {
    expect(
      NotificationService.campaignOpensHome({'type': 'campaign'}),
      isFalse,
    );
    expect(
      NotificationService.campaignOpensHome(
        {'type': 'campaign', 'screen': 'notifications'},
      ),
      isFalse,
    );
  });
}
