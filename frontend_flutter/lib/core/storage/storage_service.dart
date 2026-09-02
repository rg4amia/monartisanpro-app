import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_storage/get_storage.dart';
import 'package:uuid/uuid.dart';

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
  static const String _scoreProsArtisanKey = 'score_prosartisan';

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

  static void saveScoreProsArtisan(int score) => _box.write(_scoreProsArtisanKey, score);
  static int? getScoreProsArtisan() => _box.read<int>(_scoreProsArtisanKey);

  // ── Device Fingerprint ──────────────────────────────────────────────────────
  static const String _fingerprintKey = 'device_fingerprint';
  static String getDeviceFingerprint() {
    String? fp = _box.read<String>(_fingerprintKey);
    if (fp == null || fp.isEmpty) {
      // Lazy load import of uuid
      fp = const Uuid().v4();
      _box.write(_fingerprintKey, fp);
    }
    return fp;
  }

  // ── Driver Vehicle Settings ──────────────────────────────────────────────────
  static void saveDriverVehicle(String v) => _box.write('drv_veh', v);
  static String? getDriverVehicle() => _box.read<String>('drv_veh');

  static void saveDriverPlate(String p) => _box.write('drv_plate', p);
  static String? getDriverPlate() => _box.read<String>('drv_plate');

  static void saveDriverBasePrice(int val) => _box.write('drv_base', val);
  static int? getDriverBasePrice() => _box.read<int>('drv_base');

  static void saveDriverPriceKm(int val) => _box.write('drv_km', val);
  static int? getDriverPriceKm() => _box.read<int>('drv_km');

  static void saveDriverGps(String g) => _box.write('drv_gps', g);
  static String? getDriverGps() => _box.read<String>('drv_gps');

  static void saveDriverAddress(String a) => _box.write('drv_addr', a);
  static String? getDriverAddress() => _box.read<String>('drv_addr');

  static void saveDriverWalletBalance(int val) => _box.write('drv_wallet', val);
  static int? getDriverWalletBalance() => _box.read<int>('drv_wallet');

  // ── Notifications Preferences ───────────────────────────────────────────────
  static const String _notificationsEnabledKey = 'notifications_enabled';
  static const String _notificationSoundEnabledKey = 'notification_sound_enabled';

  static void setNotificationsEnabled(bool value) => _box.write(_notificationsEnabledKey, value);
  static bool areNotificationsEnabled() => _box.read<bool>(_notificationsEnabledKey) ?? true;

  static void setNotificationSoundEnabled(bool value) => _box.write(_notificationSoundEnabledKey, value);
  static bool isNotificationSoundEnabled() => _box.read<bool>(_notificationSoundEnabledKey) ?? true;

  // ── Clear all ───────────────────────────────────────────────────────────────
  static Future<void> clearAll() async {
    await clearToken();
    await _box.erase();
  }
}
