import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_storage/get_storage.dart';

class StorageService {
  static const FlutterSecureStorage _secure = FlutterSecureStorage();
  static final GetStorage _box = GetStorage();

  // ── Secure storage (token) ──────────────────────────────────────────────────
  static const String _tokenKey = 'auth_token';

  static Future<void> saveToken(String token) =>
      _secure.write(key: _tokenKey, value: token);

  static Future<String?> getToken() => _secure.read(key: _tokenKey);

  static Future<void> clearToken() => _secure.delete(key: _tokenKey);

  // ── GetStorage (user prefs) ─────────────────────────────────────────────────
  static const String _roleKey = 'user_role';
  static const String _userIdKey = 'user_id';
  static const String _phoneKey = 'user_phone';
  static const String _nameKey = 'user_name';
  static const String _kycStatusKey = 'kyc_status';
  static const String _onboardedKey = 'onboarded';

  static void saveRole(String role) => _box.write(_roleKey, role);
  static String? getRole() => _box.read<String>(_roleKey);

  static void saveUserId(int id) => _box.write(_userIdKey, id);
  static int? getUserId() => _box.read<int>(_userIdKey);

  static void savePhone(String phone) => _box.write(_phoneKey, phone);
  static String? getPhone() => _box.read<String>(_phoneKey);

  static void saveName(String name) => _box.write(_nameKey, name);
  static String? getName() => _box.read<String>(_nameKey);

  static void saveKycStatus(String status) => _box.write(_kycStatusKey, status);
  static String? getKycStatus() => _box.read<String>(_kycStatusKey);

  static void setOnboarded(bool value) => _box.write(_onboardedKey, value);
  static bool isOnboarded() => _box.read<bool>(_onboardedKey) ?? false;

  // ── Clear all ───────────────────────────────────────────────────────────────
  static Future<void> clearAll() async {
    await clearToken();
    _box.erase();
  }
}
