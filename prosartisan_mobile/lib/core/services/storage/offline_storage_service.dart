import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import '../../../features/auth/domain/entities/user.dart';
import '../../../features/marketplace/domain/entities/mission.dart';
import '../../../features/marketplace/domain/entities/artisan.dart';
import '../../../core/domain/value_objects/gps_coordinates.dart';
import 'database_service.dart';

/// Service for offline data storage and retrieval
class OfflineStorageService {
  final DatabaseService _databaseService = DatabaseService();

  // User operations
  Future<void> saveUser(User user) async {
    final db = await _databaseService.database;
    await db.insert('users', {
      'id': user.id,
      'email': user.email,
      'user_type': user.userType,
      'phone_number': user.phoneNumber,
      'account_status': user.accountStatus,
      'trade_category': user.tradeCategory,
      'is_kyc_verified': user.isKycVerified != null
          ? (user.isKycVerified! ? 1 : 0)
          : null,
      'business_name': user.businessName,
      'created_at': user.createdAt.toIso8601String(),
      'synced': 1,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<User?> getUser(String userId) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'users',
      where: 'id = ?',
      whereArgs: [userId],
    );

    if (maps.isEmpty) return null;

    final map = maps.first;
    return User(
      id: map['id'] as String,
      email: map['email'] as String,
      userType: map['user_type'] as String,
      phoneNumber: map['phone_number'] as String?,
      accountStatus: map['account_status'] as String,
      tradeCategory: map['trade_category'] as String?,
      isKycVerified: map['is_kyc_verified'] != null
          ? (map['is_kyc_verified'] as int) == 1
          : null,
      businessName: map['business_name'] as String?,
      createdAt: DateTime.parse(map['created_at'] as String),
    );
  }

  // Mission operations
  Future<void> saveMission(Mission mission) async {
    final db = await _databaseService.database;
    await db.insert('missions', {
      'id': mission.id,
      'client_id': mission.clientId,
      'description': mission.description,
      'category': mission.category.value,
      'latitude': mission.location.latitude,
      'longitude': mission.location.longitude,
      'budget_min': mission.budgetMin,
      'budget_max': mission.budgetMax,
      'status': mission.status.value,
      'quote_ids': jsonEncode(mission.quoteIds),
      'created_at': mission.createdAt.toIso8601String(),
      'updated_at': mission.updatedAt?.toIso8601String(),
      'synced': 1,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<List<Mission>> getMissions({String? clientId}) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'missions',
      where: clientId != null ? 'client_id = ?' : null,
      whereArgs: clientId != null ? [clientId] : null,
      orderBy: 'created_at DESC',
    );

    return maps.map((map) => _missionFromMap(map)).toList();
  }

  Future<Mission?> getMission(String missionId) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'missions',
      where: 'id = ?',
      whereArgs: [missionId],
    );

    if (maps.isEmpty) return null;
    return _missionFromMap(maps.first);
  }

  Mission _missionFromMap(Map<String, dynamic> map) {
    return Mission(
      id: map['id'],
      clientId: map['client_id'],
      description: map['description'],
      category: TradeCategory.fromString(map['category']),
      location: GPSCoordinates(
        latitude: map['latitude'],
        longitude: map['longitude'],
      ),
      budgetMin: map['budget_min'],
      budgetMax: map['budget_max'],
      status: MissionStatus.fromString(map['status']),
      quoteIds: List<String>.from(jsonDecode(map['quote_ids'] ?? '[]')),
      createdAt: DateTime.parse(map['created_at']),
      updatedAt: map['updated_at'] != null
          ? DateTime.parse(map['updated_at'])
          : null,
    );
  }

  // Artisan operations
  Future<void> saveArtisan(Artisan artisan) async {
    final db = await _databaseService.database;
    await db.insert('artisans', {
      'id': artisan.id,
      'email': artisan.email,
      'phone_number': artisan.phoneNumber,
      'category': artisan.category.value,
      'latitude': artisan.location.latitude,
      'longitude': artisan.location.longitude,
      'is_kyc_verified': artisan.isKYCVerified ? 1 : 0,
      'nzassa_score': artisan.nzassaScore,
      'average_rating': artisan.averageRating,
      'completed_projects': artisan.completedProjects,
      'profile_image_url': artisan.profileImageUrl,
      'business_name': artisan.businessName,
      'created_at': artisan.createdAt.toIso8601String(),
      'synced': 1,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<List<Artisan>> getArtisans({TradeCategory? category}) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'artisans',
      where: category != null ? 'category = ?' : null,
      whereArgs: category != null ? [category.value] : null,
      orderBy: 'nzassa_score DESC',
    );

    return maps.map((map) => _artisanFromMap(map)).toList();
  }

  Future<Artisan?> getArtisan(String artisanId) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'artisans',
      where: 'id = ?',
      whereArgs: [artisanId],
    );

    if (maps.isEmpty) return null;
    return _artisanFromMap(maps.first);
  }

  Artisan _artisanFromMap(Map<String, dynamic> map) {
    return Artisan(
      id: map['id'],
      email: map['email'],
      phoneNumber: map['phone_number'],
      category: TradeCategory.fromString(map['category']),
      location: GPSCoordinates(
        latitude: map['latitude'],
        longitude: map['longitude'],
      ),
      isKYCVerified: map['is_kyc_verified'] == 1,
      nzassaScore: map['nzassa_score'],
      averageRating: map['average_rating'],
      completedProjects: map['completed_projects'],
      profileImageUrl: map['profile_image_url'],
      businessName: map['business_name'],
      createdAt: DateTime.parse(map['created_at']),
    );
  }

  // Pending actions for offline operations
  Future<void> addPendingAction({
    required String actionType,
    required String entityType,
    required String entityId,
    required Map<String, dynamic> data,
  }) async {
    final db = await _databaseService.database;
    await db.insert('pending_actions', {
      'action_type': actionType,
      'entity_type': entityType,
      'entity_id': entityId,
      'data': jsonEncode(data),
      'created_at': DateTime.now().toIso8601String(),
      'retry_count': 0,
    });
  }

  Future<List<Map<String, dynamic>>> getPendingActions() async {
    final db = await _databaseService.database;
    return await db.query('pending_actions', orderBy: 'created_at ASC');
  }

  Future<void> removePendingAction(int actionId) async {
    final db = await _databaseService.database;
    await db.delete('pending_actions', where: 'id = ?', whereArgs: [actionId]);
  }

  Future<void> incrementRetryCount(int actionId) async {
    final db = await _databaseService.database;
    await db.update(
      'pending_actions',
      {'retry_count': 'retry_count + 1'},
      where: 'id = ?',
      whereArgs: [actionId],
    );
  }

  // Sync metadata operations
  Future<void> setSyncMetadata(String key, String value) async {
    final db = await _databaseService.database;
    await db.insert('sync_metadata', {
      'key': key,
      'value': value,
      'updated_at': DateTime.now().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getSyncMetadata(String key) async {
    final db = await _databaseService.database;
    final List<Map<String, dynamic>> maps = await db.query(
      'sync_metadata',
      where: 'key = ?',
      whereArgs: [key],
    );

    if (maps.isEmpty) return null;
    return maps.first['value'];
  }

  // Clear all offline data
  Future<void> clearAllData() async {
    await _databaseService.clearAllData();
  }
}
