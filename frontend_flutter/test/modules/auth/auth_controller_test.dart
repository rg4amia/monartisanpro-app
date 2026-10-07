import 'dart:async';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/services/otp_sms_listener.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/modules/auth/controllers/auth_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

Map<String, dynamic> _userJson({
  int id = 1,
  String phone = '+2250700000001',
  String role = 'client',
  String kycStatus = 'actif',
}) =>
    {
      'id': id,
      'phone': phone,
      'role': role,
      'kyc_status': kycStatus,
      'score_prosartisan': 0,
      'wallet_materiaux': 0,
      'wallet_mo': 0,
      'name': 'Jean Kouassi',
    };

/// Note combien de requêtes étaient déjà parties au début de chaque écoute.
class _RecordingSmsListener implements OtpSmsListener {
  _RecordingSmsListener(this._adapter);

  final FakeHttpClientAdapter _adapter;
  final List<int> requestsSeenAtStart = [];
  int cancelCount = 0;

  @override
  Future<String?> waitForCode() {
    requestsSeenAtStart.add(_adapter.requests.length);
    return Completer<String?>().future;
  }

  @override
  Future<void> cancel() async => cancelCount++;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  group('AuthController — validation des champs', () {
    test('canSendOtp exige un numéro ivoirien complet', () {
      final controller = AuthController();
      expect(controller.canSendOtp, isFalse);

      controller.phone.value = '+225070000000';
      expect(controller.canSendOtp, isFalse);

      controller.phone.value = '+2250700000001';
      expect(controller.canSendOtp, isTrue);
    });

    test('canVerifyOtp exige exactement 4 chiffres', () {
      final controller = AuthController();
      controller.otp.value = '12';
      expect(controller.canVerifyOtp, isFalse);

      controller.otp.value = '1234';
      expect(controller.canVerifyOtp, isTrue);
    });
  });

  group('AuthController.sendOtp', () {
    test('refuse un numéro invalide sans appeler le réseau', () async {
      final controller = AuthController()..phone.value = '0700000001';

      await controller.sendOtp();

      expect(adapter.requests, isEmpty);
      expect(controller.otpSent.value, isFalse);
    });

    test('envoie l\'OTP et marque otpSent à true en cas de succès', () async {
      adapter.on(
          'POST', '/auth/send-otp', const CannedResponse(statusCode: 200),);
      final controller = AuthController()..phone.value = '+2250700000001';

      await controller.sendOtp();

      expect(controller.otpSent.value, isTrue);
      expect(controller.isLoading.value, isFalse);
      expect(controller.errorMsg.value, isNull);
    });

    test('signale une erreur réseau sans planter', () async {
      // Aucune route enregistrée ⇒ connectionError simulée.
      final controller = AuthController()..phone.value = '+2250700000001';

      await controller.sendOtp();

      expect(controller.otpSent.value, isFalse);
      expect(controller.errorMsg.value, isNotNull);
      expect(controller.isLoading.value, isFalse);
    });

    test('fetchSecurityChallenge charge le défi et met à jour le token',
        () async {
      adapter.on(
        'GET',
        '/auth/security-challenge',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {
              'token': 'tok_security_xyz',
              'question': 'Combien font 3 + 4 ?',
            },
          },
        ),
      );

      final controller = AuthController();
      final res = await controller.fetchSecurityChallenge();

      expect(res, isNotNull);
      expect(controller.challengeToken.value, 'tok_security_xyz');
      expect(controller.challengeQuestion.value, 'Combien font 3 + 4 ?');
    });

    test('sendOtp transmet bot_token et bot_answer', () async {
      adapter.on(
          'POST', '/auth/send-otp', const CannedResponse(statusCode: 200),);

      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..challengeToken.value = 'tok_security_xyz'
        ..challengeQuestion.value = '3 + 4 = ?';

      await controller.sendOtp(answer: '7');

      expect(controller.otpSent.value, isTrue);
      final lastReq = adapter.requests.last;
      final body = lastReq.data as Map<String, dynamic>;
      expect(body['bot_token'], 'tok_security_xyz');
      expect(body['bot_answer'], '7');
      expect(body['bot_trap'], '');
    });

    test('un défi consommé ne repart jamais par « Renvoyer le code »',
        () async {
      // Le serveur n'accepte un défi qu'une fois. Gardé après le premier
      // envoi, il repartait au renvoi : « Ce défi de sécurité a déjà été
      // validé ou rejoué. »
      adapter.on(
        'POST',
        '/auth/send-otp',
        const CannedResponse(statusCode: 200),
      );

      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..challengeToken.value = 'tok_premier_envoi'
        ..challengeQuestion.value = '3 + 4 = ?';

      await controller.sendOtp(answer: '7');

      expect(controller.challengeToken.value, isNull);
      expect(controller.challengeQuestion.value, isNull);
      expect(controller.botAnswer.value, isEmpty);

      // Le renvoi charge un défi neuf avant de repartir.
      adapter.on(
        'GET',
        '/auth/security-challenge',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {'token': 'tok_renvoi', 'question': '2 + 5 = ?'},
          },
        ),
      );
      await controller.fetchSecurityChallenge();
      await controller.sendOtp(answer: '7');

      final sent = adapter.requests
          .where((r) => r.path.contains('send-otp'))
          .map((r) => (r.data as Map<String, dynamic>)['bot_token'])
          .toList();
      expect(sent, ['tok_premier_envoi', 'tok_renvoi']);
    });

    test('un défi refusé est abandonné lui aussi', () async {
      adapter.on(
        'POST',
        '/auth/send-otp',
        const CannedResponse(
          statusCode: 422,
          body: {
            'success': false,
            'message': 'Réponse au calcul de sécurité incorrecte.',
          },
        ),
      );

      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..challengeToken.value = 'tok_faux'
        ..challengeQuestion.value = '3 + 4 = ?';

      await controller.sendOtp(answer: '9');

      expect(controller.errorMsg.value, contains('incorrecte'));
      expect(controller.challengeToken.value, isNull);
    });
  });

  group('AuthController — écoute du SMS du code', () {
    test('l\'écoute démarre avant l\'envoi du code', () async {
      final sms = _RecordingSmsListener(adapter);
      adapter.on(
          'POST', '/auth/send-otp', const CannedResponse(statusCode: 200),);
      final controller = AuthController(smsListener: sms)
        ..phone.value = '+2250700000001';

      await controller.sendOtp();

      // Un SMS arrivé avant le début de l'écoute ne serait pas remis.
      expect(sms.requestsSeenAtStart, [0]);
      expect(adapter.requests, hasLength(1));
      expect(sms.cancelCount, 0);
    });

    test('un envoi en échec arrête l\'écoute', () async {
      final sms = _RecordingSmsListener(adapter);
      final controller = AuthController(smsListener: sms)
        ..phone.value = '+2250700000001';

      await controller.sendOtp();

      expect(controller.errorMsg.value, isNotNull);
      expect(sms.cancelCount, 1);
    });

    test('aucune écoute pour un numéro invalide', () async {
      final sms = _RecordingSmsListener(adapter);
      final controller = AuthController(smsListener: sms)
        ..phone.value = '0700000001';

      await controller.sendOtp();

      expect(sms.requestsSeenAtStart, isEmpty);
    });
  });

  group('AuthController.verifyOtp', () {
    test('refuse un OTP incomplet sans appeler le réseau', () async {
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..otp.value = '12';

      final result = await controller.verifyOtp();

      expect(result, isFalse);
      expect(adapter.requests, isEmpty);
    });

    test('profil déjà complet ⇒ session ouverte et retourne true', () async {
      adapter.on(
        'POST',
        '/auth/verify-otp',
        CannedResponse(
          statusCode: 200,
          body: {
            'has_completed_profile': true,
            'token': 'tok_123',
            'user': _userJson(),
          },
        ),
      );
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..otp.value = '1234';

      final result = await controller.verifyOtp();
      await Future<void>.delayed(Duration.zero);

      expect(result, isTrue);
      expect(controller.currentUser.value?.id, 1);
      expect(controller.isLoading.value, isFalse);
    });

    test('profil incomplet ⇒ retourne false sans lever d\'exception', () async {
      adapter.on(
        'POST',
        '/auth/verify-otp',
        const CannedResponse(
          statusCode: 200,
          body: {'has_completed_profile': false, 'token': null, 'user': null},
        ),
      );
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..otp.value = '1234';

      final result = await controller.verifyOtp();

      expect(result, isFalse);
      expect(controller.currentUser.value, isNull);
    });

    test('signale une erreur réseau et retourne false', () async {
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..otp.value = '1234';

      final result = await controller.verifyOtp();

      expect(result, isFalse);
      expect(controller.errorMsg.value, isNotNull);
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('AuthController.register', () {
    test('refuse un nom vide ou un rôle absent sans appeler le réseau',
        () async {
      final controller = AuthController()..phone.value = '+2250700000001';

      await controller.register();

      expect(adapter.requests, isEmpty);
    });

    test('crée le compte et sauvegarde le profil en cas de succès', () async {
      adapter.on(
        'POST',
        '/auth/register',
        CannedResponse(
          statusCode: 200,
          body: {'token': 'tok_abc', 'user': _userJson(role: 'artisan')},
        ),
      );
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..name.value = 'Jean Kouassi'
        ..role.value = 'artisan'
        ..cguAccepted.value = true;

      await controller.register();
      await Future<void>.delayed(Duration.zero);

      expect(controller.isLoading.value, isFalse);
      expect(controller.errorMsg.value, isNull);
      expect(StorageService.getRole(), 'artisan');
    });

    test('signale une erreur réseau sans planter', () async {
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..name.value = 'Jean Kouassi'
        ..role.value = 'artisan';

      await controller.register();

      expect(controller.errorMsg.value, isNotNull);
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('AuthController.acceptCgu', () {
    test('retourne true en cas de succès', () async {
      adapter.on(
        'POST',
        '/auth/accept-cgu',
        CannedResponse(statusCode: 200, body: {'user': _userJson()}),
      );
      final controller = AuthController();

      final result = await controller.acceptCgu();

      expect(result, isTrue);
      expect(controller.isLoading.value, isFalse);
    });

    test('retourne false et alerte en cas d\'échec réseau', () async {
      final controller = AuthController();

      final result = await controller.acceptCgu();

      expect(result, isFalse);
      expect(controller.errorMsg.value, isNotNull);
    });
  });

  group('AuthController — upload KYC', () {
    test('uploadCni ignore un chemin absent sans appeler le réseau', () async {
      final controller = AuthController();

      await controller.uploadCni();

      expect(adapter.requests, isEmpty);
    });

    test('uploadSelfie ignore un chemin absent sans appeler le réseau',
        () async {
      final controller = AuthController();

      await controller.uploadSelfie();

      expect(adapter.requests, isEmpty);
    });

    String tempSelfie() {
      final dir = Directory.systemTemp.createTempSync('selfie');
      return (File('${dir.path}/selfie.jpg')
            ..writeAsBytesSync([0xFF, 0xD8, 0xFF, 0xD9]))
          .path;
    }

    test('uploadSelfie signale un compte validé automatiquement par l\'IA',
        () async {
      adapter.on(
        'POST',
        '/kyc/upload-selfie',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {'auto_verified': true, 'kyc_status': 'actif'},
          },
        ),
      );
      final controller = AuthController()..selfiePath.value = tempSelfie();

      await controller.uploadSelfie();

      expect(controller.errorMsg.value, isNull);
      expect(controller.kycAutoVerified.value, isTrue);
      expect(StorageService.getKycStatus(), 'actif');
    });

    test('uploadSelfie laisse le dossier en attente sans validation IA',
        () async {
      adapter.on(
        'POST',
        '/kyc/upload-selfie',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {'auto_verified': false, 'kyc_status': 'en_attente'},
          },
        ),
      );
      final controller = AuthController()..selfiePath.value = tempSelfie();

      await controller.uploadSelfie();

      expect(controller.kycAutoVerified.value, isFalse);
      expect(StorageService.getKycStatus(), 'en_attente');
    });
  });

  group('AuthController.resetLocalSession', () {
    test('efface les champs du formulaire et l\'état résiduel', () async {
      final controller = AuthController()
        ..phone.value = '+2250700000001'
        ..otp.value = '1234'
        ..role.value = 'client'
        ..otpSent.value = true
        ..errorMsg.value = 'erreur précédente';

      await controller.resetLocalSession();

      expect(controller.phone.value, isEmpty);
      expect(controller.otp.value, isEmpty);
      expect(controller.role.value, isNull);
      expect(controller.otpSent.value, isFalse);
      expect(controller.errorMsg.value, isNull);
      expect(controller.isLoading.value, isFalse);
    });
  });
}
