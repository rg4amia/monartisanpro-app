import '../storage/storage_service.dart';

class DebugHelper {
  /// Clear all storage and reset app state
  static Future<void> clearAllData() async {
    await StorageService.clearAll();
    print('DEBUG: All storage cleared');
  }

  /// Print current storage state
  static Future<void> printStorageState() async {
    final token = await StorageService.getToken();
    final userId = StorageService.getUserId();
    final phone = StorageService.getPhone();
    final name = StorageService.getName();
    final role = StorageService.getRole();
    final kycStatus = StorageService.getKycStatus();
    final isOnboarded = StorageService.isOnboarded();

    print('═══════════════════════════════════════');
    print('DEBUG: Storage State');
    print('═══════════════════════════════════════');
    print('Token: ${token != null ? "${token.substring(0, 20)}..." : "NULL"}');
    print('User ID: $userId');
    print('Phone: $phone');
    print('Name: $name');
    print('Role: $role');
    print('KYC Status: $kycStatus');
    print('Is Onboarded: $isOnboarded');
    print('═══════════════════════════════════════');
  }
}
