import 'package:get/get.dart';
import '../../domain/models/reputation_profile.dart';
import '../../domain/models/score_snapshot.dart';
import '../../domain/models/rating.dart';
import '../../domain/repositories/reputation_repository.dart';
import '../../../../core/services/api/api_client.dart';

class ReputationController extends GetxController {
  final ReputationRepository _repository;
  final ApiClient _apiClient = Get.find<ApiClient>();

  ReputationController(this._repository);

  // Observable state
  final _isLoading = false.obs;
  final _reputationProfile = Rxn<ReputationProfile>();
  final _scoreHistory = <ScoreSnapshot>[].obs;
  final _ratings = <Rating>[].obs;
  final _isSubmittingRating = false.obs;

  // Getters
  bool get isLoading => _isLoading.value;
  ReputationProfile? get reputationProfile => _reputationProfile.value;
  List<ScoreSnapshot> get scoreHistory => _scoreHistory;
  List<Rating> get ratings => _ratings;
  bool get isSubmittingRating => _isSubmittingRating.value;

  /// Load artisan reputation profile
  Future<void> loadArtisanReputation(String artisanId) async {
    try {
      _isLoading.value = true;
      final token = await _apiClient.getToken();
      if (token == null) throw Exception('Token d\'authentification manquant');

      final reputation = await _repository.getArtisanReputation(
        artisanId,
        token,
      );
      _reputationProfile.value = reputation;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger le profil de réputation: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Load score history for an artisan
  Future<void> loadScoreHistory(String artisanId) async {
    try {
      _isLoading.value = true;
      final token = await _apiClient.getToken();
      if (token == null) throw Exception('Token d\'authentification manquant');

      final history = await _repository.getScoreHistory(artisanId, token);
      _scoreHistory.assignAll(history);
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger l\'historique des scores: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Load ratings for an artisan
  Future<void> loadArtisanRatings(String artisanId, {int page = 1}) async {
    try {
      _isLoading.value = true;
      final token = await _apiClient.getToken();
      if (token == null) throw Exception('Token d\'authentification manquant');

      final artisanRatings = await _repository.getArtisanRatings(
        artisanId,
        token,
        page: page,
      );
      if (page == 1) {
        _ratings.assignAll(artisanRatings);
      } else {
        _ratings.addAll(artisanRatings);
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les notes: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Submit a rating for a mission
  Future<bool> submitRating({
    required String missionId,
    required String artisanId,
    required int rating,
    String? comment,
  }) async {
    try {
      _isSubmittingRating.value = true;
      final token = await _apiClient.getToken();
      if (token == null) throw Exception('Token d\'authentification manquant');

      final newRating = await _repository.submitRating(
        missionId: missionId,
        artisanId: artisanId,
        rating: rating,
        comment: comment,
        token: token,
      );

      // Add the new rating to the list
      _ratings.insert(0, newRating);

      Get.snackbar(
        'Succès',
        'Votre note a été soumise avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de soumettre la note: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      _isSubmittingRating.value = false;
    }
  }

  /// Clear all data
  void clearData() {
    _reputationProfile.value = null;
    _scoreHistory.clear();
    _ratings.clear();
  }
}
