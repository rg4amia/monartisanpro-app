import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_endpoints.dart';
import 'package:frontend_flutter/core/utils/formatters.dart';
import 'package:frontend_flutter/data/models/jcode_model.dart';
import 'package:frontend_flutter/data/models/micro_credit_model.dart';
import 'package:frontend_flutter/data/models/mission_model.dart';
import 'package:frontend_flutter/data/models/supplier_cashout_model.dart';
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

    test('should have correct micro-credit endpoints', () {
      expect(ApiEndpoints.microCreditEligibility, '/micro-credit/eligibility');
      expect(ApiEndpoints.microCreditApply, '/micro-credit/apply');
      expect(ApiEndpoints.microCreditCurrent, '/micro-credit/current');
      expect(ApiEndpoints.microCreditRepay, '/micro-credit/repay');
      expect(ApiEndpoints.microCreditReport, '/micro-credit/report');
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

    test('parse le compteur de messages non lus et survit au cache Hive', () {
      final base = {
        'id': 42,
        'clientId': 10,
        'artisanId': 5,
        'status': 'en_cours',
        'montantTotal': 100000,
        'montantMateriaux': 60000,
        'montantMo': 40000,
        'ratioMateriaux': 0.6,
        'createdAt': '2026-02-27T10:00:00Z',
      };

      // Forme camelCase renvoyée par l'API.
      expect(
        MissionModel.fromJson({...base, 'unreadMessagesCount': 3})
            .unreadMessagesCount,
        3,
      );

      // Forme snake_case, au cas où la ressource serait servie brute.
      expect(
        MissionModel.fromJson({...base, 'unread_messages_count': 7})
            .unreadMessagesCount,
        7,
      );

      // Absent (missions servies par un endpoint sans le compteur) : 0, et
      // surtout pas une exception.
      expect(MissionModel.fromJson(base).unreadMessagesCount, 0);

      // Le compteur doit traverser la sérialisation du cache local, sinon le
      // badge disparaîtrait au rechargement hors-ligne.
      final cached = MissionModel.fromJson(
        MissionModel.fromJson({...base, 'unreadMessagesCount': 5}).toJson(),
      );
      expect(cached.unreadMessagesCount, 5);
    });

    test('should parse supplier & client coordinates from nested objects', () {
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
        'supplierCoordinates': {'lat': 5.35, 'lng': -4.02},
        'clientCoordinates': {'lat': 5.36, 'lng': -4.01},
      };

      final mission = MissionModel.fromJson(json);

      expect(mission.supplierLatitude, 5.35);
      expect(mission.supplierLongitude, -4.02);
      expect(mission.clientLatitude, 5.36);
      expect(mission.clientLongitude, -4.01);
    });

    test('supplier coordinates are null when absent', () {
      final mission = MissionModel.fromJson({
        'id': 2,
        'status': 'en_cours',
        'montantTotal': 1000,
        'ratioMateriaux': 1.0,
        'createdAt': '2026-02-27T10:00:00Z',
      });

      expect(mission.supplierLatitude, isNull);
      expect(mission.supplierLongitude, isNull);
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

  group('Traduction française des statuts (aucun terme anglais brut affiché)',
      () {
    test('normalizeStatus traduit tous les états du FSM mission', () {
      expect(
        MissionModel.normalizeStatus('pending_artisan_acceptance'),
        'en_attente',
      );
      expect(MissionModel.normalizeStatus('draft'), 'en_attente');
      expect(MissionModel.normalizeStatus('pending_funding'), 'en_attente');
      expect(MissionModel.normalizeStatus('funded_locked'), 'financee');
      expect(MissionModel.normalizeStatus('in_progress'), 'en_cours');
      expect(MissionModel.normalizeStatus('pending_approval'), 'en_cours');
      expect(MissionModel.normalizeStatus('completed'), 'terminee');
      expect(MissionModel.normalizeStatus('disputed'), 'litige');
      expect(MissionModel.normalizeStatus('cancelled'), 'annulee');
    });

    test('normalizeStatus traduit les statuts de livraison', () {
      expect(MissionModel.normalizeStatus('searching_driver'), 'en_cours');
      expect(MissionModel.normalizeStatus('prepared'), 'en_cours');
      expect(MissionModel.normalizeStatus('driver_assigned'), 'en_cours');
      expect(MissionModel.normalizeStatus('driver_picked_up'), 'en_cours');
      expect(MissionModel.normalizeStatus('shipping'), 'en_cours');
      expect(MissionModel.normalizeStatus('delivered'), 'terminee');
    });

    test('Formatters.missionStatus ne renvoie jamais un statut technique brut',
        () {
      const statutsTechniquesConnus = [
        'en_attente',
        'financee',
        'en_cours',
        'terminee',
        'litige',
        'annulee',
        'pending_artisan_acceptance',
        'cancelled',
        'searching_driver',
        'prepared',
        'driver_picked_up',
        'disputed',
        'completed',
        'refusee',
        'artisan_rejected',
      ];

      for (final statut in statutsTechniquesConnus) {
        final label = Formatters.missionStatus(statut);
        expect(
          label,
          isNot(equals(statut)),
          reason: 'Le statut "$statut" est affiché sans traduction française.',
        );
      }
      expect(Formatters.missionStatus('refusee'), 'Demande refusée');
    });

    test('MissionModel.fromJson parse correctement artisanRejected et hasArtisan', () {
      final jsonRefused = {
        'id': 42,
        'client_id': 1,
        'artisan_id': null,
        'status': 'draft',
        'artisanRejected': true,
        'hasArtisan': false,
        'montant_total': 50000,
        'montant_materiaux': 30000,
        'montant_mo': 20000,
      };

      final mission = MissionModel.fromJson(jsonRefused);
      expect(mission.id, 42);
      expect(mission.artisanRejected, isTrue);
      expect(mission.hasArtisan, isFalse);
      expect(mission.artisanId, 0);

      final jsonAssigned = {
        'id': 43,
        'client_id': 1,
        'artisan_id': 5,
        'status': 'pending_artisan_acceptance',
        'artisanRejected': false,
        'hasArtisan': true,
        'montant_total': 50000,
        'montant_materiaux': 30000,
        'montant_mo': 20000,
      };

      final missionAssigned = MissionModel.fromJson(jsonAssigned);
      expect(missionAssigned.id, 43);
      expect(missionAssigned.artisanRejected, isFalse);
      expect(missionAssigned.hasArtisan, isTrue);
      expect(missionAssigned.artisanId, 5);
    });
  });

  group('MicroCreditModel Tests', () {
    test('should parse MicroCreditEligibilityModel without active credit', () {
      final json = {
        'eligible': true,
        'score_prosartisan': 850,
        'required_score': 700,
        'max_amount': 275000,
        'total_evaluations': 12,
        'has_active_credit': false,
      };

      final model = MicroCreditEligibilityModel.fromJson(json);
      expect(model.eligible, isTrue);
      expect(model.currentScore, 850);
      expect(model.requiredScore, 700);
      expect(model.maxAmount, 275000);
      expect(model.hasActiveCredit, isFalse);
      expect(model.activeCredit, isNull);
    });

    test('should parse MicroCreditEligibilityModel with active credit', () {
      final json = {
        'eligible': false,
        'score_prosartisan': 850,
        'required_score': 700,
        'has_active_credit': true,
        'active_credit': {
          'id': 14,
          'amount': 100000,
          'repaid_amount': 30000,
          'remaining_amount': 70000,
          'status': 'debourse',
          'score_prosartisan_at_application': 850,
        },
      };

      final model = MicroCreditEligibilityModel.fromJson(json);
      expect(model.eligible, isFalse);
      expect(model.hasActiveCredit, isTrue);
      expect(model.activeCredit, isNotNull);
      expect(model.activeCredit!.id, 14);
      expect(model.activeCredit!.amount, 100000);
      expect(model.activeCredit!.repaidAmount, 30000);
      expect(model.activeCredit!.remainingAmount, 70000);
      expect(model.activeCredit!.status, 'debourse');
    });

    test('should compute remainingAmount automatically when omitted in JSON', () {
      final json = {
        'id': 15,
        'amount': 80000,
        'repaid_amount': 25000,
        'status': 'debourse',
        'score_prosartisan_at_application': 800,
      };

      final app = MicroCreditApplicationModel.fromJson(json);
      expect(app.amount, 80000);
      expect(app.repaidAmount, 25000);
      expect(app.remainingAmount, 55000);
    });
  });

  group('SupplierCashoutModel & Stats Tests', () {
    test('should parse SupplierCashoutStatsModel correctly', () {
      final json = {
        'wallet_materiaux': 150000,
        'available_balance': 110000,
        'pending_amount': 40000,
        'total_withdrawn': 350000,
        'total_requests': 6,
      };

      final stats = SupplierCashoutStatsModel.fromJson(json);
      expect(stats.walletMateriaux, 150000);
      expect(stats.availableBalance, 110000);
      expect(stats.pendingAmount, 40000);
      expect(stats.totalWithdrawn, 350000);
      expect(stats.totalRequests, 6);
    });

    test('should parse SupplierCashoutModel and compute state flags', () {
      final json = {
        'id': 42,
        'reference': 'CSH-20260925-ABCD',
        'supplier_id': 8,
        'beneficiary_name': 'Quincaillerie du Port',
        'beneficiary_phone': '+2250700112233',
        'bank_name': 'NSIA Banque',
        'bank_account_number': 'CI0920100100234567890123',
        'montant_brut': 100000,
        'commission_rate': 0.025,
        'montant_commission': 2500,
        'montant_net': 97500,
        'statut': 'complete',
        'mode_retrait': 'virement_bancaire',
        'notes': 'Virement bimensuel',
        'batch_reference': 'BATCH-20260925-01',
        'processed_at': '2026-09-25T14:30:00Z',
        'created_at': '2026-09-24T10:00:00Z',
      };

      final cashout = SupplierCashoutModel.fromJson(json);
      expect(cashout.id, 42);
      expect(cashout.reference, 'CSH-20260925-ABCD');
      expect(cashout.montantBrut, 100000);
      expect(cashout.montantCommission, 2500);
      expect(cashout.montantNet, 97500);
      expect(cashout.modeRetrait, 'virement_bancaire');
      expect(cashout.modeRetraitLabel, 'Virement bancaire');
      expect(cashout.statut, 'complete');
      expect(cashout.statutLabel, 'Complété / Décaissé');
      expect(cashout.isCompleted, isTrue);
      expect(cashout.isPending, isFalse);
      expect(cashout.isApproved, isFalse);
      expect(cashout.isRejected, isFalse);
      expect(cashout.bankName, 'NSIA Banque');
      expect(cashout.batchReference, 'BATCH-20260925-01');
    });

    test('should verify supplier cashout API endpoints', () {
      expect(ApiEndpoints.supplierCashouts, '/supplier/cashouts');
      expect(ApiEndpoints.supplierCashoutReceipt(12), '/supplier/cashouts/12/receipt');
    });
  });
}

