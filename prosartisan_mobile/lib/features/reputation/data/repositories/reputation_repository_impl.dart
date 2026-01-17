import '../../domain/models/reputation_profile.dart';
import '../../domain/models/score_snapshot.dart';
import '../../domain/models/rating.dart';
import '../../domain/repositories/reputation_repository.dart';
import '../services/reputation_api_service.dart';

class ReputationRepositoryImpl implements ReputationRepository {
  final ReputationApiService _apiService;

  ReputationRepositoryImpl(this._apiService);

  @override
  Future<ReputationProfile> getArtisanReputation(
    String artisanId,
    String token,
  ) async {
    return await _apiService.getArtisanReputation(artisanId, token);
  }

  @override
  Future<List<ScoreSnapshot>> getScoreHistory(
    String artisanId,
    String token,
  ) async {
    return await _apiService.getScoreHistory(artisanId, token);
  }

  @override
  Future<Rating> submitRating({
    required String missionId,
    required String artisanId,
    required int rating,
    String? comment,
    required String token,
  }) async {
    return await _apiService.submitRating(
      missionId: missionId,
      artisanId: artisanId,
      rating: rating,
      comment: comment,
      token: token,
    );
  }

  @override
  Future<List<Rating>> getArtisanRatings(
    String artisanId,
    String token, {
    int page = 1,
    int perPage = 20,
  }) async {
    return await _apiService.getArtisanRatings(
      artisanId,
      token,
      page: page,
      perPage: perPage,
    );
  }
}
