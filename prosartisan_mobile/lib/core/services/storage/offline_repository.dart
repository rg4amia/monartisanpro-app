import 'package:get/get.dart';
import '../../../features/auth/domain/entities/user.dart';
import '../../../features/marketplace/domain/entities/mission.dart';
import '../../../features/marketplace/domain/entities/artisan.dart';
import '../../../core/domain/value_objects/gps_coordinates.dart';
import '../api/api_service.dart';
import '../sync/sync_service.dart';
import 'offline_storage_service.dart';

/// Repository that handles both online and offline data access
class OfflineRepository extends GetxService {
  final OfflineStorageService _offlineStorage = OfflineStorageService();
  final ApiService _apiService = Get.find<ApiService>();
  final SyncService _syncService = Get.find<SyncService>();

  // Mission operations
  Future<List<Mission>> getMissions({
    String? clientId,
    bool forceOnline = false,
  }) async {
    if (forceOnline && await _syncService.isOnline) {
      try {
        // Fetch from server and cache
        final response = await _apiService.get(
          '/api/v1/missions',
          queryParameters: clientId != null ? {'client_id': clientId} : null,
        );

        final missions = (response.data['data'] as List)
            .map((json) => _missionFromJson(json))
            .toList();

        // Cache missions offline
        for (final mission in missions) {
          await _offlineStorage.saveMission(mission);
        }

        return missions;
      } catch (e) {
        // Fall back to offline data if online request fails
        return await _offlineStorage.getMissions(clientId: clientId);
      }
    } else {
      // Return offline data
      return await _offlineStorage.getMissions(clientId: clientId);
    }
  }

  Future<Mission?> getMission(
    String missionId, {
    bool forceOnline = false,
  }) async {
    if (forceOnline && await _syncService.isOnline) {
      try {
        final response = await _apiService.get('/api/v1/missions/$missionId');
        final mission = _missionFromJson(response.data);

        // Cache mission offline
        await _offlineStorage.saveMission(mission);

        return mission;
      } catch (e) {
        // Fall back to offline data
        return await _offlineStorage.getMission(missionId);
      }
    } else {
      return await _offlineStorage.getMission(missionId);
    }
  }

  Future<Mission> createMission(Mission mission) async {
    if (await _syncService.isOnline) {
      try {
        final response = await _apiService.post('/api/v1/missions', {
          'description': mission.description,
          'category': mission.category.value,
          'location': {
            'latitude': mission.location.latitude,
            'longitude': mission.location.longitude,
          },
          'budget_min': mission.budgetMin,
          'budget_max': mission.budgetMax,
        });

        final createdMission = _missionFromJson(response.data);
        await _offlineStorage.saveMission(createdMission);

        return createdMission;
      } catch (e) {
        // Save offline and add to pending actions
        await _offlineStorage.saveMission(mission);
        await _syncService.addPendingAction(
          actionType: 'CREATE',
          entityType: 'mission',
          entityId: mission.id,
          data: {
            'description': mission.description,
            'category': mission.category.value,
            'location': {
              'latitude': mission.location.latitude,
              'longitude': mission.location.longitude,
            },
            'budget_min': mission.budgetMin,
            'budget_max': mission.budgetMax,
          },
        );

        return mission;
      }
    } else {
      // Save offline and add to pending actions
      await _offlineStorage.saveMission(mission);
      await _syncService.addPendingAction(
        actionType: 'CREATE',
        entityType: 'mission',
        entityId: mission.id,
        data: {
          'description': mission.description,
          'category': mission.category.value,
          'location': {
            'latitude': mission.location.latitude,
            'longitude': mission.location.longitude,
          },
          'budget_min': mission.budgetMin,
          'budget_max': mission.budgetMax,
        },
      );

      return mission;
    }
  }

  // Artisan operations
  Future<List<Artisan>> getArtisans({
    TradeCategory? category,
    bool forceOnline = false,
  }) async {
    if (forceOnline && await _syncService.isOnline) {
      try {
        final response = await _apiService.get(
          '/api/v1/artisans/search',
          queryParameters: category != null
              ? {'category': category.value}
              : null,
        );

        final artisans = (response.data['data'] as List)
            .map((json) => _artisanFromJson(json))
            .toList();

        // Cache artisans offline
        for (final artisan in artisans) {
          await _offlineStorage.saveArtisan(artisan);
        }

        return artisans;
      } catch (e) {
        // Fall back to offline data
        return await _offlineStorage.getArtisans(category: category);
      }
    } else {
      return await _offlineStorage.getArtisans(category: category);
    }
  }

  Future<Artisan?> getArtisan(
    String artisanId, {
    bool forceOnline = false,
  }) async {
    if (forceOnline && await _syncService.isOnline) {
      try {
        final response = await _apiService.get('/api/v1/artisans/$artisanId');
        final artisan = _artisanFromJson(response.data);

        // Cache artisan offline
        await _offlineStorage.saveArtisan(artisan);

        return artisan;
      } catch (e) {
        // Fall back to offline data
        return await _offlineStorage.getArtisan(artisanId);
      }
    } else {
      return await _offlineStorage.getArtisan(artisanId);
    }
  }

  // User operations
  Future<void> saveUser(User user) async {
    await _offlineStorage.saveUser(user);
  }

  Future<User?> getUser(String userId) async {
    return await _offlineStorage.getUser(userId);
  }

  // Rating operations
  Future<void> submitRating({
    required String missionId,
    required int rating,
    String? comment,
  }) async {
    final ratingData = {
      'mission_id': missionId,
      'rating': rating,
      'comment': comment,
    };

    if (await _syncService.isOnline) {
      try {
        await _apiService.post('/api/v1/missions/$missionId/rate', ratingData);
      } catch (e) {
        // Add to pending actions if online request fails
        await _syncService.addPendingAction(
          actionType: 'RATING',
          entityType: 'rating',
          entityId: missionId,
          data: ratingData,
        );
      }
    } else {
      // Add to pending actions for offline submission
      await _syncService.addPendingAction(
        actionType: 'RATING',
        entityType: 'rating',
        entityId: missionId,
        data: ratingData,
      );
    }
  }

  // Quote operations
  Future<void> submitQuote({
    required String missionId,
    required double totalAmount,
    required double materialsAmount,
    required double laborAmount,
    required List<Map<String, dynamic>> lineItems,
  }) async {
    final quoteData = {
      'mission_id': missionId,
      'total_amount': totalAmount,
      'materials_amount': materialsAmount,
      'labor_amount': laborAmount,
      'line_items': lineItems,
    };

    if (await _syncService.isOnline) {
      try {
        await _apiService.post('/api/v1/missions/$missionId/quotes', quoteData);
      } catch (e) {
        // Add to pending actions if online request fails
        await _syncService.addPendingAction(
          actionType: 'QUOTE_SUBMISSION',
          entityType: 'quote',
          entityId: missionId,
          data: quoteData,
        );
      }
    } else {
      // Add to pending actions for offline submission
      await _syncService.addPendingAction(
        actionType: 'QUOTE_SUBMISSION',
        entityType: 'quote',
        entityId: missionId,
        data: quoteData,
      );
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
}
