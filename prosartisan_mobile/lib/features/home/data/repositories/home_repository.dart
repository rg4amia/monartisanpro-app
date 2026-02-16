import 'package:get/get.dart';
import '../../../../core/services/api/api_service.dart';
import '../../../../core/constants/api_constants.dart';
import '../../../../shared/models/category_model.dart';
import '../models/service_model.dart';

/// Repository for home page data
class HomeRepository {
  final ApiService _apiService = Get.find<ApiService>();

  /// Fetch popular services/missions
  Future<List<ServiceModel>> getPopularServices({
    int limit = 10,
    String? category,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'limit': limit,
        'popular': true,
        if (category != null) 'category': category,
      };

      final response = await _apiService.get(
        ApiConstants.missions,
        queryParameters: queryParams,
      );

      final data = response.data['data'] as List<dynamic>? ?? [];
      return data
          .map(
            (service) => ServiceModel.fromJson(service as Map<String, dynamic>),
          )
          .toList();
    } catch (e) {
      throw Exception('Failed to load popular services: $e');
    }
  }

  /// Fetch all services/missions
  Future<List<ServiceModel>> getAllServices({
    int page = 1,
    int limit = 20,
    String? search,
    String? category,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'limit': limit,
        if (search != null && search.isNotEmpty) 'search': search,
        if (category != null) 'category': category,
      };

      final response = await _apiService.get(
        ApiConstants.missions,
        queryParameters: queryParams,
      );

      final data = response.data['data'] as List<dynamic>? ?? [];
      return data
          .map(
            (service) => ServiceModel.fromJson(service as Map<String, dynamic>),
          )
          .toList();
    } catch (e) {
      throw Exception('Failed to load services: $e');
    }
  }

  /// Fetch categories (using sectors as categories)
  Future<List<CategoryModel>> getCategories() async {
    try {
      final response = await _apiService.get(ApiConstants.categories);

      final data = response.data['data'] as List<dynamic>? ?? [];
      return data
          .map(
            (category) =>
                CategoryModel.fromJson(category as Map<String, dynamic>),
          )
          .toList();
    } catch (e) {
      throw Exception('Failed to load categories: $e');
    }
  }

  /// Search artisans
  Future<List<ServiceModel>> searchArtisans({
    required String query,
    String? location,
    String? category,
    double? maxDistance,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'q': query,
        if (location != null) 'location': location,
        if (category != null) 'category': category,
        if (maxDistance != null) 'max_distance': maxDistance,
      };

      final response = await _apiService.get(
        ApiConstants.searchArtisans,
        queryParameters: queryParams,
      );

      final data = response.data['data'] as List<dynamic>? ?? [];
      return data
          .map(
            (artisan) => ServiceModel.fromJson(artisan as Map<String, dynamic>),
          )
          .toList();
    } catch (e) {
      throw Exception('Failed to search artisans: $e');
    }
  }

  /// Get user profile information
  Future<Map<String, dynamic>> getUserProfile() async {
    try {
      final response = await _apiService.get(ApiConstants.userProfile);
      return response.data['data'] as Map<String, dynamic>? ?? {};
    } catch (e) {
      throw Exception('Failed to load user profile: $e');
    }
  }

  /// Get notifications count
  Future<int> getNotificationsCount() async {
    try {
      final response = await _apiService.get(
        '${ApiConstants.notifications}?unread_count=true',
      );
      return response.data['unread_count'] as int? ?? 0;
    } catch (e) {
      // Return 0 if notifications endpoint is not available
      return 0;
    }
  }
}
