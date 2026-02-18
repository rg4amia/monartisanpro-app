import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/escrow_model.dart';
import 'dio_client.dart';

class VendorService {
  final Dio _dio = DioClient().dio;

  /// Get vendor statistics
  Future<ApiResponse<VendorStats>> getStats() async {
    try {
      final response = await _dio.get('/vendors/stats');

      return ApiResponse<VendorStats>.fromJson(
        response.data,
        (json) => VendorStats.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<VendorStats>.fromJson(
          e.response!.data,
          (json) => VendorStats.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get vendor redemption history
  Future<ApiResponse<List<TokenRedemption>>> getRedemptions({
    String? validationMethod,
    String? startDate,
    String? endDate,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};

      if (validationMethod != null) {
        queryParams['validation_method'] = validationMethod;
      }
      if (startDate != null) {
        queryParams['start_date'] = startDate;
      }
      if (endDate != null) {
        queryParams['end_date'] = endDate;
      }

      final response = await _dio.get(
        '/vendors/redemptions',
        queryParameters: queryParams,
      );

      return ApiResponse<List<TokenRedemption>>.fromJson(response.data, (json) {
        final data = json as Map<String, dynamic>;
        final items = data['data'] as List;
        return items
            .map(
              (item) => TokenRedemption.fromJson(item as Map<String, dynamic>),
            )
            .toList();
      });
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<TokenRedemption>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get vendor transactions
  Future<ApiResponse<List<Transaction>>> getTransactions({
    String? status,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};

      if (status != null) {
        queryParams['status'] = status;
      }

      final response = await _dio.get(
        '/vendors/transactions',
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

  /// Get vendor analytics
  Future<ApiResponse<VendorAnalytics>> getAnalytics({
    String period = 'month',
  }) async {
    try {
      final response = await _dio.get(
        '/vendors/analytics',
        queryParameters: {'period': period},
      );

      return ApiResponse<VendorAnalytics>.fromJson(
        response.data,
        (json) => VendorAnalytics.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<VendorAnalytics>.fromJson(
          e.response!.data,
          (json) => VendorAnalytics.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }
}

/// Vendor Statistics Model
class VendorStats {
  final int tokensValidated;
  final double totalAmount;
  final double pendingAmount;
  final double completedAmount;
  final DateRange period;

  VendorStats({
    required this.tokensValidated,
    required this.totalAmount,
    required this.pendingAmount,
    required this.completedAmount,
    required this.period,
  });

  factory VendorStats.fromJson(Map<String, dynamic> json) {
    return VendorStats(
      tokensValidated: json['tokens_validated'] as int,
      totalAmount: (json['total_amount'] as num).toDouble(),
      pendingAmount: (json['pending_amount'] as num).toDouble(),
      completedAmount: (json['completed_amount'] as num).toDouble(),
      period: DateRange.fromJson(json['period'] as Map<String, dynamic>),
    );
  }
}

/// Vendor Analytics Model
class VendorAnalytics {
  final String period;
  final DateRange dateRange;
  final List<DataPoint> validationTrend;
  final List<MethodBreakdown> methodsBreakdown;
  final List<TopProject> topProjects;
  final double averageAmount;
  final double validationRate;
  final int totalValidations;

  VendorAnalytics({
    required this.period,
    required this.dateRange,
    required this.validationTrend,
    required this.methodsBreakdown,
    required this.topProjects,
    required this.averageAmount,
    required this.validationRate,
    required this.totalValidations,
  });

  factory VendorAnalytics.fromJson(Map<String, dynamic> json) {
    return VendorAnalytics(
      period: json['period'] as String,
      dateRange: DateRange.fromJson(json['date_range'] as Map<String, dynamic>),
      validationTrend: (json['validation_trend'] as List)
          .map((item) => DataPoint.fromJson(item as Map<String, dynamic>))
          .toList(),
      methodsBreakdown: (json['methods_breakdown'] as List)
          .map((item) => MethodBreakdown.fromJson(item as Map<String, dynamic>))
          .toList(),
      topProjects: (json['top_projects'] as List)
          .map((item) => TopProject.fromJson(item as Map<String, dynamic>))
          .toList(),
      averageAmount: (json['average_amount'] as num).toDouble(),
      validationRate: (json['validation_rate'] as num).toDouble(),
      totalValidations: json['total_validations'] as int,
    );
  }
}

class DateRange {
  final String start;
  final String end;

  DateRange({required this.start, required this.end});

  factory DateRange.fromJson(Map<String, dynamic> json) {
    return DateRange(
      start: json['start'] as String,
      end: json['end'] as String,
    );
  }
}

class DataPoint {
  final String date;
  final int count;
  final double total;

  DataPoint({required this.date, required this.count, required this.total});

  factory DataPoint.fromJson(Map<String, dynamic> json) {
    return DataPoint(
      date: json['date'] as String,
      count: json['count'] as int,
      total: (json['total'] as num).toDouble(),
    );
  }
}

class MethodBreakdown {
  final String validationMethod;
  final int count;

  MethodBreakdown({required this.validationMethod, required this.count});

  factory MethodBreakdown.fromJson(Map<String, dynamic> json) {
    return MethodBreakdown(
      validationMethod: json['validation_method'] as String,
      count: json['count'] as int,
    );
  }
}

class TopProject {
  final int id;
  final String title;
  final int redemptionCount;
  final double totalAmount;

  TopProject({
    required this.id,
    required this.title,
    required this.redemptionCount,
    required this.totalAmount,
  });

  factory TopProject.fromJson(Map<String, dynamic> json) {
    return TopProject(
      id: json['id'] as int,
      title: json['title'] as String,
      redemptionCount: json['redemption_count'] as int,
      totalAmount: (json['total_amount'] as num).toDouble(),
    );
  }
}
