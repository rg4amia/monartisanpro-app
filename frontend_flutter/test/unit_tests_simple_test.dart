import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_endpoints.dart';
import 'package:frontend_flutter/data/models/jcode_model.dart';
import 'package:frontend_flutter/data/models/mission_model.dart';
import 'package:frontend_flutter/data/models/user_model.dart';

/// Tests unitaires simples sans dépendances natives
/// Ces tests peuvent être exécutés sans émulateur
void main() {
  group('API Configuration Tests', () {
    test('should use correct Herd backend URL', () {
      // Note: EnvConfig.baseUrl returns emulatorBaseUrl in non-production mode
      expect(
        ApiEndpoints.baseUrl,
        anyOf(
          'http://backend-proartisan.test/api/v1',
          'http://10.0.2.2:8000/api/v1',
          'http://127.0.0.1:8000/api/v1',
        ),
      );
    });

    test('should have correct auth endpoints', () {
      expect(ApiEndpoints.sendOtp, '/auth/send-otp');
      expect(ApiEndpoints.verifyOtp, '/auth/verify-otp');
      expect(ApiEndpoints.register, '/auth/register');
      expect(ApiEndpoints.me, '/auth/me');
      expect(ApiEndpoints.logout, '/auth/logout');
    });

    test('should have correct mission endpoints', () {
      expect(ApiEndpoints.missions, '/missions');
      expect(ApiEndpoints.mission(1), '/missions/1');
      expect(ApiEndpoints.missionStatus(1), '/missions/1/status');
      expect(ApiEndpoints.missionDevis(1), '/missions/1/devis');
    });

    test('should have correct J-Code endpoints', () {
      expect(ApiEndpoints.jcodes, '/jcodes');
      expect(ApiEndpoints.jcodesActive, '/jcodes/active');
      expect(ApiEndpoints.jcode(1), '/jcodes/1');
      expect(ApiEndpoints.scanJcode(1), '/jcodes/1/scan');
    });
  });

  group('UserModel Tests', () {
    test('should create UserModel from JSON', () {
      final json = {
        'id': 1,
        'phone': '+2250700000001',
        'role': 'client',
        'kycStatus': 'actif',
        'scoreProsArtisan': 70,
        'walletMateriaux': 50000,
        'walletMo': 30000,
        'name': 'Test User',
      };

      final user = UserModel.fromJson(json);

      expect(user.id, 1);
      expect(user.phone, '+2250700000001');
      expect(user.role, 'client');
      expect(user.kycStatus, 'actif');
      expect(user.scoreProsArtisan, 70);
      expect(user.name, 'Test User');
    });

    test('should handle missing optional fields', () {
      final json = {
        'id': 1,
        'phone': '+2250700000001',
        'role': 'client',
      };

      final user = UserModel.fromJson(json);

      expect(user.kycStatus, 'en_attente');
      expect(user.scoreProsArtisan, 0);
      expect(user.name, isNull);
    });

    test('isKycActif should return correct value', () {
      const userActif = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'client',
        kycStatus: 'actif',
        scoreProsArtisan: 50,
        walletMateriaux: 0,
        walletMo: 0,
      );

      const userEnAttente = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'client',
        kycStatus: 'en_attente',
        scoreProsArtisan: 50,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(userActif.isKycActif, true);
      expect(userEnAttente.isKycActif, false);
    });

    test('isGoldenMarker should return correct value', () {
      const highScore = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 720,
        walletMateriaux: 0,
        walletMo: 0,
      );

      const lowScore = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 650,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(highScore.isGoldenMarker, true);
      expect(lowScore.isGoldenMarker, false);
    });

    test('should convert UserModel to JSON', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 80,
        walletMateriaux: 100000,
        walletMo: 50000,
        name: 'Artisan Test',
      );

      final json = user.toJson();

      expect(json['id'], 1);
      expect(json['phone'], '+2250700000001');
      expect(json['role'], 'artisan');
      expect(json['name'], 'Artisan Test');
    });
  });

  group('JcodeModel Tests', () {
    test('should create JcodeModel from JSON', () {
      final json = {
        'id': 1,
        'missionId': 10,
        'artisanId': 5,
        'code': 'PA-1234',
        'montant': 50000,
        'statut': 'actif',
        'expiresAt': '2026-03-01T12:00:00Z',
      };

      final jcode = JcodeModel.fromJson(json);

      expect(jcode.id, 1);
      expect(jcode.missionId, 10);
      expect(jcode.artisanId, 5);
      expect(jcode.code, 'PA-1234');
      expect(jcode.montant, 50000);
      expect(jcode.statut, 'actif');
    });

    test(
        'should create JcodeModel and JcodeItemModel from JSON with partial consumption fields',
        () {
      final json = {
        'id': 1,
        'missionId': 10,
        'artisanId': 5,
        'code': 'PA-1234',
        'montant': 50000,
        'montantConsomme': 20000,
        'statut': 'partiellement_utilise',
        'expiresAt': '2026-03-01T12:00:00Z',
        'items': [
          {
            'id': 1,
            'source': 'custom',
            'name': 'Ciment',
            'quantity': 10,
            'quantityServed': 4,
            'unitPrice': 5000,
            'subtotal': 50000,
            'status': 'partial',
          }
        ],
      };

      final jcode = JcodeModel.fromJson(json);

      expect(jcode.id, 1);
      expect(jcode.montantConsomme, 20000);
      expect(jcode.montantRestant, 30000);
      expect(jcode.statut, 'partiellement_utilise');
      expect(jcode.isPartiallyUsed, true);
      expect(jcode.isActive, true);

      expect(jcode.items.length, 1);
      final item = jcode.items.first;
      expect(item.quantity, 10);
      expect(item.quantityServed, 4);
      expect(item.remainingQuantity, 6);
      expect(item.isPartial, true);
      expect(item.isServed, false);
      expect(item.isRequested, false);
    });

    test('isActive should return correct value', () {
      const activeJcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'actif',
        expiresAt: '2026-03-01T12:00:00Z',
      );

      const usedJcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'utilise',
        expiresAt: '2026-03-01T12:00:00Z',
      );

      expect(activeJcode.isActive, true);
      expect(usedJcode.isActive, false);
    });

    test('isUsed should return correct value', () {
      const usedJcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'utilise',
        expiresAt: '2026-03-01T12:00:00Z',
      );

      expect(usedJcode.isUsed, true);
    });

    test('isExpired should return correct value', () {
      const expiredJcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'expire',
        expiresAt: '2026-03-01T12:00:00Z',
      );

      expect(expiredJcode.isExpired, true);
    });

    test('should convert JcodeModel to JSON', () {
      const jcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'actif',
        expiresAt: '2026-03-01T12:00:00Z',
        qrUrl: 'https://example.com/qr.png',
      );

      final json = jcode.toJson();

      expect(json['id'], 1);
      expect(json['code'], 'PA-1234');
      expect(json['montant'], 50000);
      expect(json['qrUrl'], 'https://example.com/qr.png');
    });
  });

  group('MissionModel Tests', () {
    test('should create MissionModel from JSON', () {
      final json = {
        'id': 1,
        'clientId': 10,
        'artisanId': 5,
        'status': 'en_cours',
        'montantTotal': 100000,
        'montantMateriaux': 60000,
        'montantMo': 40000,
        'ratioMateriaux': 0.6,
        'createdAt': '2026-02-27T10:00:00Z',
        'description': 'Réparer une fuite',
        'category': 'plomberie',
        'urgency': 'normale',
      };

      final mission = MissionModel.fromJson(json);

      expect(mission.id, 1);
      expect(mission.clientId, 10);
      expect(mission.artisanId, 5);
      expect(mission.description, 'Réparer une fuite');
      expect(mission.category, 'plomberie');
      expect(mission.urgency, 'normale');
      expect(mission.status, 'en_cours');
      expect(mission.montantTotal, 100000);
    });

    test('should convert MissionModel to JSON', () {
      const mission = MissionModel(
        id: 1,
        clientId: 10,
        artisanId: 5,
        status: 'en_cours',
        montantTotal: 100000,
        montantMateriaux: 60000,
        montantMo: 40000,
        ratioMateriaux: 0.6,
        createdAt: '2026-02-27T10:00:00Z',
        description: 'Réparer une fuite',
        category: 'plomberie',
        urgency: 'normale',
      );

      final json = mission.toJson();

      expect(json['id'], 1);
      expect(json['description'], 'Réparer une fuite');
      expect(json['category'], 'plomberie');
      expect(json['status'], 'en_cours');
      expect(json['montant_total'], 100000);
    });

    test('needsReferent should return true for missions > 2M', () {
      const highValueMission = MissionModel(
        id: 1,
        clientId: 10,
        artisanId: 5,
        status: 'en_cours',
        montantTotal: 2500000,
        montantMateriaux: 1500000,
        montantMo: 1000000,
        ratioMateriaux: 0.6,
        createdAt: '2026-02-27T10:00:00Z',
      );

      const lowValueMission = MissionModel(
        id: 1,
        clientId: 10,
        artisanId: 5,
        status: 'en_cours',
        montantTotal: 100000,
        montantMateriaux: 60000,
        montantMo: 40000,
        ratioMateriaux: 0.6,
        createdAt: '2026-02-27T10:00:00Z',
      );

      expect(highValueMission.needsReferent, true);
      expect(lowValueMission.needsReferent, false);
    });
  });

  group('Business Logic Tests', () {
    test('Score ProsArtisan calculation logic', () {
      // Score >= 700 (échelle 0–1000) = Golden Marker
      const highScoreUser = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 720,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(highScoreUser.isGoldenMarker, true);
      expect(highScoreUser.scoreProsArtisan >= 700, true);
    });

    test('KYC status validation', () {
      const validStatuses = ['en_attente', 'en_cours', 'actif', 'refuse'];

      for (final status in validStatuses) {
        final user = UserModel(
          id: 1,
          phone: '+2250700000001',
          role: 'client',
          kycStatus: status,
          scoreProsArtisan: 0,
          walletMateriaux: 0,
          walletMo: 0,
        );

        expect(user.kycStatus, status);
      }
    });

    test('J-Code format validation', () {
      const jcode = JcodeModel(
        id: 1,
        missionId: 10,
        artisanId: 5,
        code: 'PA-1234',
        montant: 50000,
        statut: 'actif',
        expiresAt: '2026-03-01T12:00:00Z',
      );

      expect(jcode.code, startsWith('PA-'));
      expect(jcode.code.length, greaterThan(3));
    });

    test('Wallet amounts should be non-negative', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 70,
        walletMateriaux: 50000,
        walletMo: 30000,
      );

      expect(user.walletMateriaux, greaterThanOrEqualTo(0));
      expect(user.walletMo, greaterThanOrEqualTo(0));
    });
  });

  group('Driver Role Tests', () {
    test('UserModel should parse driver role', () {
      final json = {
        'id': 100,
        'phone': '+2250700000005',
        'role': 'driver',
        'kycStatus': 'actif',
      };
      final user = UserModel.fromJson(json);
      expect(user.role, 'driver');
    });

    test('MissionModel should expose rawStatus', () {
      const mission = MissionModel(
        id: 301,
        clientId: 1,
        artisanId: 2,
        status: 'en_cours',
        statusGemini: 'driver_assigned',
        montantTotal: 10000,
        montantMateriaux: 8000,
        montantMo: 2000,
        ratioMateriaux: 0.8,
        createdAt: '2026-03-01T12:00:00Z',
      );
      expect(mission.rawStatus, 'driver_assigned');
    });
  });
}
