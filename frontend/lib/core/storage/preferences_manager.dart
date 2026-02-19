import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Manages app preferences and settings
class PreferencesManager {
  static final PreferencesManager _instance = PreferencesManager._internal();
  factory PreferencesManager() => _instance;

  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  PreferencesManager._internal();

  // Keys
  static const String _hasSeenOnboardingKey = 'has_seen_onboarding';
  static const String _rememberMeKey = 'remember_me';
  static const String _lastLoginEmailKey = 'last_login_email';

  /// Check if user has seen onboarding
  Future<bool> hasSeenOnboarding() async {
    final value = await _storage.read(key: _hasSeenOnboardingKey);
    return value == 'true';
  }

  /// Mark onboarding as seen
  Future<void> setOnboardingSeen() async {
    await _storage.write(key: _hasSeenOnboardingKey, value: 'true');
  }

  /// Check if remember me is enabled
  Future<bool> isRememberMeEnabled() async {
    final value = await _storage.read(key: _rememberMeKey);
    return value == 'true';
  }

  /// Set remember me preference
  Future<void> setRememberMe(bool value) async {
    await _storage.write(key: _rememberMeKey, value: value.toString());
  }

  /// Get last login email
  Future<String?> getLastLoginEmail() async {
    return await _storage.read(key: _lastLoginEmailKey);
  }

  /// Save last login email
  Future<void> saveLastLoginEmail(String email) async {
    await _storage.write(key: _lastLoginEmailKey, value: email);
  }

  /// Clear last login email
  Future<void> clearLastLoginEmail() async {
    await _storage.delete(key: _lastLoginEmailKey);
  }

  /// Clear all preferences
  Future<void> clearAll() async {
    await _storage.delete(key: _hasSeenOnboardingKey);
    await _storage.delete(key: _rememberMeKey);
    await _storage.delete(key: _lastLoginEmailKey);
  }
}
