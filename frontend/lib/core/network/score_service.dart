import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/scoring_model.dart';
import 'dio_client.dart';

class ScoreService {
  final Dio _dio = DioClient().dio;

  /// Get artisan's current score
  Future<ApiResponse<ArtisanScore>> getArtisanScore(int artisanId) async {
    try {
      final response = await _dio.get('/scores/$artisanId');

      return ApiResponse<ArtisanScore>.fromJson(
        response.data,
        (json) => ArtisanScore.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ArtisanScore>.fromJson(
          e.response!.data,
          (json) => ArtisanScore.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get score history for an artisan
  Future<ApiResponse<List<ScoreHistory>>> getScoreHistory(
    int artisanId, {
    int? limit,
  }) async {
    try {
      final response = await _dio.get(
        '/scores/$artisanId/history',
        queryParameters: limit != null ? {'limit': limit} : null,
      );

      return ApiResponse<List<ScoreHistory>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => ScoreHistory.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<ScoreHistory>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get reviews for an artisan
  Future<ApiResponse<List<Review>>> getArtisanReviews(
    int artisanId, {
    int? limit,
    int? offset,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (limit != null) queryParams['limit'] = limit;
      if (offset != null) queryParams['offset'] = offset;

      final response = await _dio.get(
        '/reviews',
        queryParameters: queryParams.isNotEmpty ? queryParams : null,
      );

      return ApiResponse<List<Review>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => Review.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Review>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get review statistics for an artisan
  Future<ApiResponse<ReviewStats>> getReviewStats(int artisanId) async {
    try {
      // TODO: Backend doesn't have this endpoint yet - create it
      final response = await _dio.get('/reviews?artisan_id=$artisanId');

      return ApiResponse<ReviewStats>.fromJson(
        response.data,
        (json) => ReviewStats.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ReviewStats>.fromJson(
          e.response!.data,
          (json) => ReviewStats.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Create a review for a project
  Future<ApiResponse<Review>> createReview(CreateReviewRequest request) async {
    try {
      final response = await _dio.post('/reviews', data: request.toJson());

      return ApiResponse<Review>.fromJson(
        response.data,
        (json) => Review.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Review>.fromJson(
          e.response!.data,
          (json) => Review.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Add artisan response to a review
  Future<ApiResponse<Review>> respondToReview(
    int reviewId,
    String response,
  ) async {
    try {
      final responseData = await _dio.post('/reviews/$reviewId/respond', data: {
        'response': response,
      });

      return ApiResponse<Review>.fromJson(
        responseData.data,
        (json) => Review.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Review>.fromJson(
          e.response!.data,
          (json) => Review.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Upload review photos
  Future<ApiResponse<List<String>>> uploadReviewPhotos(
    List<String> photoPaths,
  ) async {
    try {
      final formData = FormData();

      for (var i = 0; i < photoPaths.length; i++) {
        formData.files.add(MapEntry(
          'photos[$i]',
          await MultipartFile.fromFile(photoPaths[i]),
        ));
      }

      final response = await _dio.post('/reviews/upload-photos', data: formData);

      return ApiResponse<List<String>>.fromJson(
        response.data,
        (json) => ((json as Map<String, dynamic>)['photo_urls'] as List).cast<String>(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<String>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }
}
