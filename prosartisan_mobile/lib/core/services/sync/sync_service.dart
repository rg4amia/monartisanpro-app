import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:get/get.dart';
import '../storage/offline_storage_service.dart';
import '../api/api_service.dart';
import '../../../features/auth/domain/entities/user.dart';
import '../../../features/marketplace/domain/entities/mission.dart';
import '../../../features/marketplace/domain/entities/artisan.dart';

/// Service for synchronizing offline data with the server
class SyncService extends GetxService {
  final OfflineStorageService _offlineStorage = OfflineStorageService();
  final ApiService _apiService = Get.find<ApiService>();
  final Connectivity _connectivity = Connectivity();

  final RxBool isSyncing = false.obs;
  final RxString syncStatus = ''.obs;
  final RxInt pendingActionsCount = 0.obs;

  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  Timer? _syncTimer;

  @override
  void onInit() {
    super.onInit();
    _initConnectivityListener();
    _startPeriodicSync();
    _updatePendingActionsCount();
  }

  @override
  void onClose() {
    _connectivitySubscription?.cancel();
    _syncTimer?.cancel();
    super.onClose();
  }

  /// Initialize connectivity listener
  void _initConnectivityListener() {
    _connectivitySubscription = _connectivity.onConnectivityChanged.listen(
      (List<ConnectivityResult> results) {
        final isConnected = results.any((result) => 
          result == ConnectivityResult.mobile || 
          result == ConnectivityResult.wifi
        );
        
        if (isConnected) {
          syncPendingActions();
        }
      },
    );
  }

  /// Start periodic sync every 5 minutes when online
  void _startPeriodicSync() {
    _syncTimer = Timer.periodic(const Duration(minutes: 5), (_) {
      syncPendingActions();
    });
  }

  /// Check if device is online
  Future<bool> get isOnline async {
    final connectivityResults = await _connectivity.checkConnectivity();
    return connectivityResults.any((result) => 
      result == ConnectivityResult.mobile || 
      result == ConnectivityResult.wifi
    );
  }

  /// Sync all pending actions with the server
  Future<void> syncPendingActions() async {
    if (isSyncing.value || !await isOnline) return;

    try {
      isSyncing.value = true;
      syncStatus.value = 'Synchronisation en cours...';

      final pendingActions = await _offlineStorage.getPendingActions();
      
      for (final action in pendingActions) {
        try {
          await _processPendingAction(action);
          await _offlineStorage.removePendingAction(action['id']);
        } catch (e) {
          // Increment retry count and continue with next action
          await _offlineStorage.incrementRetryCount(action['id']);
          
          // Remove action if retry count exceeds 3
          if (action['retry_count'] >= 3) {
            await _offlineStorage.removePendingAction(action['id']);
          }
        }
      }

      await _updatePendingActionsCount();
      syncStatus.value = 'Synchronisation terminée';
      
      // Clear status after 3 seconds
      Timer(const Duration(seconds: 3), () {
        syncStatus.value = '';
      });

    } catch (e) {
      syncStatus.value = 'Erreur de synchronisation';
      Timer(const Duration(seconds: 3), () {
        syncStatus.value = '';
      });
    } finally {
      isSyncing.value = false;
    }
  }

  /// Process a single pending action
  Future<void> _processPendingAction(Map<String, dynamic> action) async {
    final actionType = action['action_type'];
    final entityType = action['entity_type'];
    final entityId = action['entity_id'];
    final data = jsonDecode(action['data']);

    switch (actionType) {
      case 'CREATE':
        await _handleCreateAction(entityType, data);
        break;
      case 'UPDATE':
        await _handleUpdateAction(entityType, entityId, data);
        break;
      case 'DELETE':
        await _handleDeleteAction(entityType, entityId);
        break;
      case 'RATING':
        await _handleRatingAction(data);
        break;
      case 'QUOTE_SUBMISSION':
        await _handleQuoteSubmissionAction(data);
        break;
      default:
        throw Exception('Unknown action type: $actionType');
    }
  }

  /// Handle create actions
  Future<void> _handleCreateAction(String entityType, Map<String, dynamic> data) async {
    switch (entityType) {
      case 'mission':
        await _apiService.post('/api/v1/missions', data);
        break;
      case 'user':
        await _apiService.post('/api/v1/auth/register', data);
        break;
      default:
        throw Exception('Unknown entity type for create: $entityType');
    }
  }

  /// Handle update actions
  Future<void> _handleUpdateAction(String entityType, String entityId, Map<String, dynamic> data) async {
    switch (entityType) {
      case 'mission':
        await _apiService.put('/api/v1/missions/$entityId', data);
        break;
      case 'user':
        await _apiService.put('/api/v1/users/$entityId', data);
        break;
      default:
        throw Exception('Unknown entity type for update: $entityType');
    }
  }

  /// Handle delete actions
  Future<void> _handleDeleteAction(String entityType, String entityId) async {
    switch (entityType) {
      case 'mission':
        await _apiService.delete('/api/v1/missions/$entityId');
        break;
      default:
        throw Exception('Unknown entity type for delete: $entityType');
    }
  }

  /// Handle rating submission
  Future<void> _handleRatingAction(Map<String, dynamic> data) async {
    final missionId = data['mission_id'];
    await _apiService.post('/api/v1/missions/$missionId/rate', data);
  }

  /// Handle quote submission
  Future<void> _handleQuoteSubmissionAction(Map<String, dynamic> data) async {
    final missionId = data['mission_id'];
    await _apiService.post('/api/v1/missions/$missionId/quotes', data);
  }

  /// Add pending action for offline operations
  Future<void> addPendingAction({
    required String actionType,
    required String entityType,
    required String entityId,
    required Map<String, dynamic> data,
  }) async {
    await _offlineStorage.addPendingAction(
      actionType: actionType,
      entityType: entityType,
      entityId: entityId,
      data: data,
    );
    await _updatePendingActionsCount();
  }

  /// Update pending actions count
  Future<void> _updatePendingActionsCount() async {
    final actions = await _offlineStorage.getPendingActions();
    pendingActionsCount.value = actions.length;
  }

  /// Sync fresh data from server (when coming online)
  Future<void> syncFromServer() async {
    if (!await isOnline) return;

    try {
      isSyncing.value = true;
      syncStatus.value = 'Récupération des données...';

      // Get last sync timestamp
      final lastSync = await _offlineStorage.getSyncMetadata('last_sync');
      final timestamp = lastSync != null 
          ? DateTime.parse(lastSync) 
          : DateTime.now().subtract(const Duration(days: 7));

      // Sync missions
      await _syncMissionsFromServer(timestamp);
      
      // Sync artisans
      await _syncArtisansFromServer(timestamp);

      // Update last sync timestamp
      await _offlineStorage.setSyncMetadata(
        'last_sync', 
        DateTime.now().toIso8601String(),
      );

      syncStatus.value = 'Données mises à jour';
      Timer(const Duration(seconds: 3), () {
        syncStatus.value = '';
      });

    } catch (e) {
      syncStatus.value = 'Erreur de mise à jour';
      Timer(const Duration(seconds: 3), () {
        syncStatus.value = '';
      });
    } finally {
      isSyncing.value = false;
    }
  }

  /// Sync missions from server
  Future<void> _syncMissionsFromServer(DateTime since) async {
    try {
      final response = await _apiService.get(
        '/api/v1/missions',
        queryParameters: {
          'since': since.toIso8601String(),
          'limit': 100,
        },
      );

      final missions = (response.data['data'] as List)
          .map((json) => _missionFromJson(json))
          .toList();

      for (final mission in missions) {
        await _offlineStorage.saveMission(mission);
      }
    } catch (e) {
      // Log error but don't throw to allow other syncs to continue
      print('Error syncing missions: $e');
    }
  }

  /// Sync artisans from server
  Future<void> _syncArtisansFromServer(DateTime since) async {
    try {
      final response = await _apiService.get(
        '/api/v1/artisans',
        queryParameters: {
          'since': since.toIso8601String(),
          'limit': 100,
        },
      );

      final artisans = (response.data['data'] as List)
          .map((json) => _artisanFromJson(json))
          .toList();

      for (final artisan in artisans) {
        await _offlineStorage.saveArtisan(artisan);
      }
    } catch (e) {
      // Log error but don't throw to allow other syncs to continue
      print('Error syncing artisans: $e');
    }
  }

  /// Convert JSON to Mission entity
  Mission _missionFromJson(Map<String, dynamic> json) {
    return Mission(
      id: json['id'],
      clientId: json['client_id'],
      description: json['description'],
      category: TradeCategory.fromString(json['category']),
      location: GPSCoordinates(
        latitude: json['location']['latitude'],
        longitude: json['location']['longitude'],
      ),
      budgetMin: json['budget_min'].toDouble(),
      budgetMax: json['budget_max'].toDouble(),
      status: MissionStatus.fromString(json['status']),
      quoteIds: List<String>.from(json['quote_ids'] ?? []),
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: json['updated_at'] != null 
          ? DateTime.parse(json['updated_at']) 
          : null,
    );
  }

  /// Convert JSON to Artisan entity
  Artisan _artisanFromJson(Map<String, dynamic> json) {
    return Artisan(
      id: json['id'],
      email: json['email'],
      phoneNumber: json['phone_number'],
      category: TradeCategory.fromString(json['category']),
      location: GPSCoordinates(
        latitude: json['location']['latitude'],
        longitude: json['location']['longitude'],
      ),
      isKYCVerified: json['is_kyc_verified'] ?? false,
      nzassaScore: json['nzassa_score']?.toDouble() ?? 0.0,
      averageRating: json['average_rating']?.toDouble() ?? 0.0,
      completedProjects: json['completed_projects'] ?? 0,
      profileImageUrl: json['profile_image_url'],
      businessName: json['business_name'],
      createdAt: DateTime.parse(json['created_at']),
    );
  }

  /// Force sync now (manual trigger)
  Future<void> forceSyncNow() async {
    await syncPendingActions();
    await syncFromServer();
  }
}