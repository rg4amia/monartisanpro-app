import 'package:dio/dio.dart';
import '../../domain/models/reputation_profile.dart';
import '../../domain/models/score_snapshot.dart';
import '../../domain/models/rating.dart';
import '../../../../core/constants/api_constants.dart';

class ReputationApiService {
  final Dio _dio;
  final String _baseUrl = ApiConstants.baseUrl;

  ReputationApiService({Dio? dio}) : _dio = dio ?? Dio();

  /// Get artisan reputation profile
  Future<ReputationProfile> getArtisanReputation(String artisanId, String token) async {
    try {
      final response = await _dio.get(
        '$_baseUrl/v1/artisans/$artisanId/reputation',
        options: Options(
          headers: {
            'Authorization': 'Bearer $token',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      if (response.statusCode == 200) {
        return ReputationProfile.fromJson(response.data['data']);
      } else {
        throw Exception('Erreur lors de la récupération du profil de réputation');
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw Exception('Profil de réputation non trouvé');
      } else {
        throw Exception('Erreur lors de la récupération du profil de réputation');
      }
    }
  }

  /// Get artisan score history
  Future<List<ScoreSnapshot>> getScoreHistory(String artisanId, String token) async {
    try {
      final response = await _dio.get(
        '$_baseUrl/v1/artisans/$artisanId/score-history',
        options: Options(
          headers: {
            'Authorization': 'Bearer $token',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      if (response.statusCode == 200) {
        final List<dynamic> historyData = response.data['data'];
        return historyData.map((item) => ScoreSnapshot.fromJson(item)).toList();
      } else {
        throw Exception('Erreur lors de la récupération de l\'historique des scores');
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw Exception('Historique des scores non trouvé');
      } else {
        throw Exception('Erreur lors de la récupération de l\'historique des scores');
      }
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
      final response = await _dio.post(
        '$_baseUrl/v1/missions/$missionId/rate',
        data: {
          'artisan_id': artisanId,
          'rating': rating,
          'comment': comment,
        },
        options: Options(
          headers: {
            'Authorization': 'Bearer $token',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      if (response.statusCode == 201) {
        return Rating.fromJson(response.data['data']);
      } else {
        throw Exception('Erreur lors de la soumission de la note');
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 409) {
        throw Exception('Une note a déjà été soumise pour cette mission');
      } else {
        throw Exception('Erreur lors de la soumission de la note');
      }
      Uri.parse(
        '$_baseUrl/v1/artisans/$artisanId/ratings?page=$page&per_page=$perPage',
      ),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      final List<dynamic> ratingsData = data['data'] ?? data;
      return ratingsData.map((item) => Rating.fromJson(item)).toList();
    } else {
      throw Exception('Erreur lors de la récupération des notes');
    }
  }
}
