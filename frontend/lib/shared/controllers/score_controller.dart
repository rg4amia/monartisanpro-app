import 'package:get/get.dart';
import '../../core/network/score_service.dart';
import '../models/scoring_model.dart';

class ScoreController extends GetxController {
  final ScoreService _scoreService = Get.find<ScoreService>();

  // Score state
  final Rx<ArtisanScore?> artisanScore = Rx<ArtisanScore?>(null);
  final RxList<ScoreHistory> scoreHistory = <ScoreHistory>[].obs;
  final RxList<Review> reviews = <Review>[].obs;
  final Rx<ReviewStats?> reviewStats = Rx<ReviewStats?>(null);

  // Loading states
  final RxBool isLoading = false.obs;
  final RxBool isLoadingHistory = false.obs;
  final RxBool isLoadingReviews = false.obs;
  final RxBool isSubmittingReview = false.obs;
  final RxString errorMessage = ''.obs;

  // Pagination
  final RxInt reviewsOffset = 0.obs;
  final RxBool hasMoreReviews = true.obs;

  /// Fetch artisan score
  Future<void> fetchArtisanScore(int artisanId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _scoreService.getArtisanScore(artisanId);

      if (response.success && response.data != null) {
        artisanScore.value = response.data;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch score';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch score history
  Future<void> fetchScoreHistory(int artisanId, {int? limit}) async {
    try {
      isLoadingHistory.value = true;

      final response = await _scoreService.getScoreHistory(
        artisanId,
        limit: limit,
      );

      if (response.success && response.data != null) {
        scoreHistory.value = response.data!;
      }
    } catch (e) {
      // Silent fail for history
      scoreHistory.value = [];
    } finally {
      isLoadingHistory.value = false;
    }
  }

  /// Fetch reviews
  Future<void> fetchReviews(
    int artisanId, {
    bool loadMore = false,
    int limit = 10,
  }) async {
    try {
      if (loadMore) {
        reviewsOffset.value += limit;
      } else {
        isLoadingReviews.value = true;
        reviewsOffset.value = 0;
      }

      final response = await _scoreService.getArtisanReviews(
        artisanId,
        limit: limit,
        offset: reviewsOffset.value,
      );

      if (response.success && response.data != null) {
        if (loadMore) {
          reviews.addAll(response.data!);
        } else {
          reviews.value = response.data!;
        }

        hasMoreReviews.value = response.data!.length >= limit;
      }
    } catch (e) {
      // Silent fail
    } finally {
      isLoadingReviews.value = false;
    }
  }

  /// Fetch review statistics
  Future<void> fetchReviewStats(int artisanId) async {
    try {
      final response = await _scoreService.getReviewStats(artisanId);

      if (response.success && response.data != null) {
        reviewStats.value = response.data;
      }
    } catch (e) {
      // Silent fail
    }
  }

  /// Submit a review
  Future<bool> submitReview(CreateReviewRequest request) async {
    try {
      isSubmittingReview.value = true;
      errorMessage.value = '';

      final response = await _scoreService.createReview(request);

      if (response.success && response.data != null) {
        // Add to reviews list
        reviews.insert(0, response.data!);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to submit review';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isSubmittingReview.value = false;
    }
  }

  /// Respond to a review (artisan only)
  Future<bool> respondToReview(int reviewId, String responseText) async {
    try {
      errorMessage.value = '';

      final response = await _scoreService.respondToReview(
        reviewId,
        responseText,
      );

      if (response.success && response.data != null) {
        // Update the review in the list
        final index = reviews.indexWhere((r) => r.id == reviewId);
        if (index != -1) {
          reviews[index] = response.data!;
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to post response';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    }
  }

  /// Upload review photos
  Future<List<String>?> uploadReviewPhotos(List<String> photoPaths) async {
    try {
      errorMessage.value = '';

      final response = await _scoreService.uploadReviewPhotos(photoPaths);

      if (response.success && response.data != null) {
        return response.data;
      } else {
        errorMessage.value = response.message ?? 'Failed to upload photos';
        return null;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return null;
    }
  }

  /// Refresh all score data
  Future<void> refreshScoreData(int artisanId) async {
    await Future.wait([
      fetchArtisanScore(artisanId),
      fetchScoreHistory(artisanId, limit: 10),
      fetchReviews(artisanId),
      fetchReviewStats(artisanId),
    ]);
  }

  /// Reset state
  void reset() {
    artisanScore.value = null;
    scoreHistory.clear();
    reviews.clear();
    reviewStats.value = null;
    reviewsOffset.value = 0;
    hasMoreReviews.value = true;
    errorMessage.value = '';
  }
}
