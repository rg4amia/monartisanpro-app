import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
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

  group('AuthController — réinitialisation téléphone perdu', () {
    test('canSendResetOtp exige tous les champs', () {
      final controller = AuthController();
      expect(controller.canSendResetOtp, isFalse);

      controller
        ..resetOldPhone.value = '+2250700000001'
        ..resetNewPhone.value = '+2250700000002'
        ..resetName.value = 'Jean Kouassi'
        ..resetRole.value = 'client';

      expect(controller.canSendResetOtp, isTrue);
    });

    test('requestResetPhone refuse un formulaire incomplet sans réseau',
        () async {
      final controller = AuthController();

      await controller.requestResetPhone();

      expect(adapter.requests, isEmpty);
      expect(controller.isResetOtpSent.value, isFalse);
    });

    test('requestResetPhone marque isResetOtpSent en cas de succès', () async {
      adapter.on('POST', '/auth/reset-phone-request',
          const CannedResponse(statusCode: 200),);
      final controller = AuthController()
        ..resetOldPhone.value = '+2250700000001'
        ..resetNewPhone.value = '+2250700000002'
        ..resetName.value = 'Jean Kouassi'
        ..resetRole.value = 'client';

      await controller.requestResetPhone();

      expect(controller.isResetOtpSent.value, isTrue);
      expect(controller.isResetting.value, isFalse);
    });

    test('confirmResetPhone refuse un OTP incomplet sans réseau', () async {
      final controller = AuthController();

      final result = await controller.confirmResetPhone();

      expect(result, isFalse);
      expect(adapter.requests, isEmpty);
    });

    test('confirmResetPhone ouvre la session avec le nouveau numéro', () async {
      adapter.on(
        'POST',
        '/auth/reset-phone-confirm',
        CannedResponse(
          statusCode: 200,
          body: {
            'token': 'tok_reset',
            'user': _userJson(phone: '+2250700000002'),
          },
        ),
      );
      final controller = AuthController()
        ..resetOldPhone.value = '+2250700000001'
        ..resetNewPhone.value = '+2250700000002'
        ..resetName.value = 'Jean Kouassi'
        ..resetRole.value = 'client'
        ..resetOtp.value = '1234';

      final result = await controller.confirmResetPhone();

      expect(result, isTrue);
      expect(controller.isResetting.value, isFalse);
    });

    test('confirmResetPhone signale une erreur réseau sans planter', () async {
      final controller = AuthController()
        ..resetOldPhone.value = '+2250700000001'
        ..resetNewPhone.value = '+2250700000002'
        ..resetName.value = 'Jean Kouassi'
        ..resetRole.value = 'client'
        ..resetOtp.value = '1234';

      final result = await controller.confirmResetPhone();

      expect(result, isFalse);
      expect(controller.errorMsg.value, isNotNull);
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
