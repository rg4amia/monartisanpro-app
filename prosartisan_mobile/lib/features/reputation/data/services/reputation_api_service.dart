import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';
import '../../domain/models/reputation_profile.dart';
import '../../domain/models/score_snapshot.dart';
import '../../domain/models/rating.dart';

class ReputationApiService {
  final ApiClient _apiClient;

  ReputationApiService(this._apiClient);

  /// Get artisan reputation profile
  Future<ReputationProfile> getArtisanReputation(
    String artisanId,
    String token,
  ) async {
    try {
      final path = ApiConstants.artisanReputation.replaceAll('{id}', artisanId);
      final response = await _apiClient.get(path);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return ReputationProfile.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw Exception('Profil de réputation non trouvé');
      } else {
        throw Exception(
          'Erreur lors de la récupération du profil de réputation',
        );
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception(
        'Erreur lors de la récupération du profil de réputation: ${e.toString()}',
      );
    }
  }

  /// Get artisan score history
  Future<List<ScoreSnapshot>> getScoreHistory(
    String artisanId,
    String token,
  ) async {
    try {
      final path = ApiConstants.artisanScoreHistory.replaceAll('{id}', artisanId);
      final response = await _apiClient.get(path);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data']
          : responseData;

      if (data is! List) {
        throw Exception('Invalid score history format');
      }

      final List<dynamic> historyData = data;
      return historyData.map((item) => ScoreSnapshot.fromJson(item)).toList();
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw Exception('Historique des scores non trouvé');
      } else {
        throw Exception(
          'Erreur lors de la récupération de l\'historique des scores',
        );
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception(
        'Erreur lors de la récupération de l\'historique des scores: ${e.toString()}',
      );
    }
  }

  /// Submit rating for a mission
  Future<Rating> submitRating({
    required String missionId,
    required String artisanId,
    required int rating,
    String? comment,
    required String token,
  }) async {
    try {
      final path = ApiConstants.missionRate.replaceAll('{id}', missionId);
      final response = await _apiClient.post(
        path,
        data: {
          'artisan_id': artisanId,
          'rating': rating,
          if (comment != null) 'comment': comment,
        },
      );

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Rating.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 409) {
        throw Exception('Une note a déjà été soumise pour cette mission');
      } else if (e.response?.statusCode == 403) {
        throw Exception('Vous n\'avez pas la permission de noter cette mission');
      } else {
        throw Exception('Erreur lors de la soumission de la note');
      }
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Erreur lors de la soumission de la note: ${e.toString()}');
    }
  }

  /// Get all ratings for an artisan
  Future<List<Rating>> getArtisanRatings(
    String artisanId,
    String token, {
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final path = ApiConstants.artisanRatings.replaceAll('{id}', artisanId);
      final response = await _apiClient.get(
        path,
        queryParameters: {'page': page, 'per_page': perPage},
      );

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data']
          : responseData;

      // Handle both array and paginated responses
      final List<dynamic> ratingsData;
      if (data is List) {
        ratingsData = data;
      } else if (data is Map<String, dynamic> && data.containsKey('data')) {
        ratingsData = data['data'] as List<dynamic>;
      } else {
        throw Exception('Invalid ratings data format');
      }

      return ratingsData.map((item) => Rating.fromJson(item)).toList();
    } on DioException {
      throw Exception('Erreur lors de la récupération des notes');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Erreur lors de la récupération des notes: ${e.toString()}');
    }
  }
}
