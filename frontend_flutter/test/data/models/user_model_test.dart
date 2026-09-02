import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/user_model.dart';

void main() {
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
        'photoUrl': 'https://example.com/photo.jpg',
        'lat': 5.3599517,
        'lng': -4.0082563,
      };

      final user = UserModel.fromJson(json);

      expect(user.id, 1);
      expect(user.phone, '+2250700000001');
      expect(user.role, 'client');
      expect(user.kycStatus, 'actif');
      expect(user.scoreProsArtisan, 70);
      expect(user.walletMateriaux, 50000);
      expect(user.walletMo, 30000);
      expect(user.name, 'Test User');
      expect(user.photoUrl, 'https://example.com/photo.jpg');
      expect(user.lat, 5.3599517);
      expect(user.lng, -4.0082563);
    });

    test('should handle missing optional fields', () {
      final json = {
        'id': 1,
        'phone': '+2250700000001',
        'role': 'client',
      };

      final user = UserModel.fromJson(json);

      expect(user.id, 1);
      expect(user.kycStatus, 'en_attente');
      expect(user.scoreProsArtisan, 0);
      expect(user.walletMateriaux, 0);
      expect(user.walletMo, 0);
      expect(user.name, isNull);
      expect(user.photoUrl, isNull);
      expect(user.lat, isNull);
      expect(user.lng, isNull);
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
      expect(json['kycStatus'], 'actif');
      expect(json['scoreProsArtisan'], 80);
      expect(json['name'], 'Artisan Test');
    });

    test('isKycActif should return true when status is actif', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'client',
        kycStatus: 'actif',
        scoreProsArtisan: 50,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(user.isKycActif, true);
    });

    test('isKycActif should return false when status is not actif', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'client',
        kycStatus: 'en_attente',
        scoreProsArtisan: 50,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(user.isKycActif, false);
    });

    test('isGoldenMarker should return true when score >= 700', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 720,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(user.isGoldenMarker, true);
    });

    test('isGoldenMarker should return false when score < 700', () {
      const user = UserModel(
        id: 1,
        phone: '+2250700000001',
        role: 'artisan',
        kycStatus: 'actif',
        scoreProsArtisan: 650,
        walletMateriaux: 0,
        walletMo: 0,
      );

      expect(user.isGoldenMarker, false);
    });
  });
}
