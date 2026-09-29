import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/artisan_availability_model.dart';
import 'package:frontend_flutter/modules/settings/controllers/availability_controller.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// « Ma disponibilité » (Chantier 15) : la déclaration de l'artisan attend la
/// validation d'un administrateur ; la version publiée reste affichée.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  const published = {
    'status': 'disponible',
    'effective_label': 'Disponible',
    'review_status': 'validee',
    // Entier ou chaîne : la lecture reste défensive (Règle d'or 28).
    'schedule': [
      {'day': 1, 'start': '08:00', 'end': '17:00'},
      {'day': '6', 'start': '08:00', 'end': '12:00'},
    ],
    'schedule_summary': 'Lundi 08:00–17:00 · Samedi 08:00–12:00',
    'night_work': true,
  };

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
  });

  test(
      'pré-remplit le formulaire avec la demande en attente plutôt que la version publiée',
      () async {
    adapter.on(
      'GET',
      '/artisan/availability',
      const CannedResponse(
        statusCode: 200,
        body: {
          'success': true,
          'data': {
            'published': published,
            'pending': {
              'status': 'conge',
              'effective_label': 'En congé jusqu\'au 20/10/2026',
              'until_date': '2026-10-20',
              'review_status': 'en_attente',
              'schedule': [],
              'night_work': false,
            },
            'last_rejected': null,
          },
        },
      ),
    );

    final controller = AvailabilityController();
    await controller.load();

    expect(controller.errorMsg.value, isNull);
    expect(controller.overview.value!.published!.slots, hasLength(2));
    expect(controller.overview.value!.published!.slots.last.day, 6);
    expect(controller.status.value, 'conge');
    expect(controller.untilDate.value, DateTime(2026, 10, 20));
    expect(controller.slots, isEmpty);
    expect(controller.nightWork.value, isFalse);
  });

  test(
      'une panne s\'affiche comme telle, jamais comme une absence de disponibilité',
      () async {
    adapter.on(
      'GET',
      '/artisan/availability',
      const CannedResponse(statusCode: 500, body: {'message': 'Erreur'}),
    );

    final controller = AvailabilityController();
    await controller.load();

    expect(controller.errorMsg.value, isNotNull);
    expect(controller.overview.value, isNull);
  });

  test('un statut occupé exige une date de retour avant l\'envoi', () async {
    adapter.on(
      'GET',
      '/artisan/availability',
      const CannedResponse(
        statusCode: 200,
        body: {
          'success': true,
          'data': {'published': null, 'pending': null, 'last_rejected': null},
        },
      ),
    );
    adapter.on(
      'POST',
      '/artisan/availability',
      const CannedResponse(
        statusCode: 201,
        body: {
          'success': true,
          'data': {
            'published': null,
            'pending': {
              'status': 'occupe',
              'effective_label': 'Occupé jusqu\'au 05/11/2026',
              'until_date': '2026-11-05',
              'review_status': 'en_attente',
            },
          },
        },
      ),
    );

    final controller = AvailabilityController();
    await controller.load();

    controller.setStatus('occupe');
    expect(controller.canSubmit, isFalse);

    controller.untilDate.value = DateTime(2026, 11, 5);
    controller.slots.add(
      const AvailabilitySlot(day: 2, start: '07:30', end: '16:00'),
    );
    controller.nightWork.value = true;
    await controller.submit();

    final request = adapter.requests.last;
    expect(request.method, 'POST');
    expect(request.data, {
      'status': 'occupe',
      'until_date': '2026-11-05',
      'schedule': [
        {'day': 2, 'start': '07:30', 'end': '16:00'},
      ],
      'night_work': true,
    });
    expect(controller.submitError.value, isNull);
    expect(controller.submitSuccess.value, contains('après validation'));
    expect(controller.overview.value!.pending!.reviewStatus, 'en_attente');
  });

  test('le refus du serveur est montré à l\'artisan', () async {
    adapter.on(
      'GET',
      '/artisan/availability',
      const CannedResponse(
        statusCode: 200,
        body: {'success': true, 'data': {}},
      ),
    );
    adapter.on(
      'POST',
      '/artisan/availability',
      const CannedResponse(
        statusCode: 422,
        body: {
          'message': 'Cette disponibilité est identique à celle déjà publiée.',
          'errors': {
            'status': [
              'Cette disponibilité est identique à celle déjà publiée.',
            ],
          },
        },
      ),
    );

    final controller = AvailabilityController();
    await controller.load();
    await controller.submit();

    expect(controller.submitError.value, isNotNull);
    expect(controller.submitSuccess.value, isNull);
  });
}
