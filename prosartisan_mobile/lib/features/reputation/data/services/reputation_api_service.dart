import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../domain/models/reputation_profile.dart';
import '../../domain/models/score_snapshot.dart';
import '../../domain/models/rating.dart';
import '../../../../core/constants/api_constants.dart';

class ReputationApiService {
  final http.Client _client;
  final String _baseUrl = ApiConstants.baseUrl;

  ReputationApiService({http.Client? client})
    : _client = client ?? http.Client();

  /// Get artisan reputation profile
  Future<ReputationProfile> getArtisanReputation(
    String artisanId,
    String token,
  ) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/v1/artisans/$artisanId/reputation'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return ReputationProfile.fromJson(data['data']);
    } else if (response.statusCode == 404) {
      throw Exception('Profil de réputation non trouvé');
    } else {
      throw Exception('Erreur lors de la récupération du profil de réputation');
    }
  }

  /// Get artisan score history
  Future<List<ScoreSnapshot>> getScoreHistory(
    String artisanId,
    String token,
  ) async {
    final response = await _client.get(
      Uri.parse('$_baseUrl/v1/artisans/$artisanId/score-history'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      final List<dynamic> historyData = data['data'];
      return historyData.map((item) => ScoreSnapshot.fromJson(item)).toList();
    } else if (response.statusCode == 404) {
      throw Exception('Historique des scores non trouvé');
    } else {
      throw Exception(
        'Erreur lors de la récupération de l\'historique des scores',
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
    final response = await _client.post(
      Uri.parse('$_baseUrl/v1/missions/$missionId/rate'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: json.encode({
        'artisan_id': artisanId,
        'rating': rating,
        'comment': comment,
      }),
    );

    if (response.statusCode == 201) {
      final data = json.decode(response.body);
      return Rating.fromJson(data['data']);
    } else if (response.statusCode == 409) {
      throw Exception('Une note a déjà été soumise pour cette mission');
    } else {
      throw Exception('Erreur lors de la soumission de la note');
    }
  }

  /// Get all ratings for an artisan
  Future<List<Rating>> getArtisanRatings(
    String artisanId,
    String token, {
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _client.get(
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
