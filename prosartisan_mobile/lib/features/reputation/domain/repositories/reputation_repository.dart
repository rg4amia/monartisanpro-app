import '../models/reputation_profile.dart';
import '../models/score_snapshot.dart';
import '../models/rating.dart';

abstract class ReputationRepository {
  Future<ReputationProfile> getArtisanReputation(
    String artisanId,
    String token,
  );
  Future<List<ScoreSnapshot>> getScoreHistory(String artisanId, String token);
  Future<Rating> submitRating({
    required String missionId,
    required String artisanId,
    required int rating,
    String? comment,
    required String token,
  });
  Future<List<Rating>> getArtisanRatings(
    String artisanId,
    String token, {
    int page = 1,
    int perPage = 20,
  });
}
