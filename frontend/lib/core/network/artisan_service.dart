import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/escrow_model.dart';
import '../../shared/models/project_model.dart';
import '../../shared/models/scoring_model.dart';
import 'dio_client.dart';

class ArtisanService {
  final Dio _dio = DioClient().dio;

  /// Get artisan statistics
  Future<ApiResponse<ArtisanStats>> getStats() async {
    try {
      final response = await _dio.get('/artisans/stats');

      return ApiResponse<ArtisanStats>.fromJson(
        response.data,
        (json) => ArtisanStats.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ArtisanStats>.fromJson(
          e.response!.data,
          (json) => ArtisanStats.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get artisan quotes
  Future<ApiResponse<List<Quote>>> getQuotes({
    String? status,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};

      if (status != null && status != 'all') {
        queryParams['status'] = status;
      }

      final response = await _dio.get(
        '/artisans/quotes',
        queryParameters: queryParams,
      );

      return ApiResponse<List<Quote>>.fromJson(response.data, (json) {
        final data = json as Map<String, dynamic>;
        final items = data['data'] as List;
        return items
            .map((item) => Quote.fromJson(item as Map<String, dynamic>))
            .toList();
      });
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Quote>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get artisan transactions
  Future<ApiResponse<List<Transaction>>> getTransactions({
    String? status,
    String? transactionType,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};

      if (status != null) {
        queryParams['status'] = status;
      }
      if (transactionType != null) {
        queryParams['transaction_type'] = transactionType;
      }

      final response = await _dio.get(
        '/artisans/transactions',
        queryParameters: queryParams,
      );

      return ApiResponse<List<Transaction>>.fromJson(response.data, (json) {
        final data = json as Map<String, dynamic>;
        final items = data['data'] as List;
        return items
            .map((item) => Transaction.fromJson(item as Map<String, dynamic>))
            .toList();
      });
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Transaction>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get artisan score
  Future<ApiResponse<ArtisanScore>> getScore() async {
    try {
      final response = await _dio.get('/artisans/score');

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
}

/// Artisan Statistics Model
class ArtisanStats {
  final ProjectStats projects;
  final QuoteStats quotes;
  final EarningsStats earnings;
  final ScoreInfo? score;

  ArtisanStats({
    required this.projects,
    required this.quotes,
    required this.earnings,
    this.score,
  });

  factory ArtisanStats.fromJson(Map<String, dynamic> json) {
    return ArtisanStats(
      projects: ProjectStats.fromJson(json['projects'] as Map<String, dynamic>),
      quotes: QuoteStats.fromJson(json['quotes'] as Map<String, dynamic>),
      earnings: EarningsStats.fromJson(
        json['earnings'] as Map<String, dynamic>,
      ),
      score: json['score'] != null
          ? ScoreInfo.fromJson(json['score'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ProjectStats {
  final int active;
  final int completed;
  final int total;

  ProjectStats({
    required this.active,
    required this.completed,
    required this.total,
  });

  factory ProjectStats.fromJson(Map<String, dynamic> json) {
    return ProjectStats(
      active: json['active'] as int,
      completed: json['completed'] as int,
      total: json['total'] as int,
    );
  }
}

class QuoteStats {
  final int pending;
  final int accepted;
  final int total;

  QuoteStats({
    required this.pending,
    required this.accepted,
    required this.total,
  });

  factory QuoteStats.fromJson(Map<String, dynamic> json) {
    return QuoteStats(
      pending: json['pending'] as int,
      accepted: json['accepted'] as int,
      total: json['total'] as int,
    );
  }
}

class EarningsStats {
  final double total;
  final double pending;

  EarningsStats({required this.total, required this.pending});

  factory EarningsStats.fromJson(Map<String, dynamic> json) {
    return EarningsStats(
      total: (json['total'] as num).toDouble(),
      pending: (json['pending'] as num).toDouble(),
    );
  }
}

class ScoreInfo {
  final double total;
  final String? badge;

  ScoreInfo({required this.total, this.badge});

  factory ScoreInfo.fromJson(Map<String, dynamic> json) {
    return ScoreInfo(
      total: (json['total'] as num).toDouble(),
      badge: json['badge'] as String?,
    );
  }
}
