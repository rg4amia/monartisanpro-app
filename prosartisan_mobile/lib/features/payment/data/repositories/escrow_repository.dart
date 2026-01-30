import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';

/// Repository for escrow-related API operations
///
/// Requirements: 4.1, 4.2, 4.3
class EscrowRepository {
  final ApiClient _apiClient;

  EscrowRepository(this._apiClient);

  /// Block funds in escrow when quote is accepted
  ///
  /// Requirement 4.1: Block funds in escrow when quote is accepted by client
  Future<Map<String, dynamic>> blockEscrow({
    required String quoteId,
    required int totalAmountCentimes,
    required int materialsAmountCentimes,
    required int laborAmountCentimes,
    required String clientId,
    required String artisanId,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.escrowBlock,
        data: {
          'quote_id': quoteId,
          'total_amount_centimes': totalAmountCentimes,
          'materials_amount_centimes': materialsAmountCentimes,
          'labor_amount_centimes': laborAmountCentimes,
          'client_id': clientId,
          'artisan_id': artisanId,
        },
      );

      // Check if response data is null
      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      // Check if response data is a Map
      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;

      // Extract the 'data' field if it exists (Laravel API format)
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Escrow blocking failed: ${e.toString()}');
    }
  }

  /// Get escrow status for a quote
  Future<Map<String, dynamic>> getEscrowStatus(String quoteId) async {
    try {
      final response = await _apiClient.get(
        '/escrow/status/$quoteId',
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

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to get escrow status: ${e.toString()}');
    }
  }

  /// Release materials funds from escrow (via jeton)
  ///
  /// Requirement 4.2: Release materials funds via jeton system
  Future<Map<String, dynamic>> releaseMaterialsFunds({
    required String sequestreId,
    required int amountCentimes,
  }) async {
    try {
      final response = await _apiClient.post(
        '/escrow/release/materials',
        data: {
          'sequestre_id': sequestreId,
          'amount_centimes': amountCentimes,
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

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to release materials funds: ${e.toString()}');
    }
  }

  /// Release labor funds from escrow (upon milestone validation)
  ///
  /// Requirement 4.3: Release labor funds upon milestone validation
  Future<Map<String, dynamic>> releaseLaborFunds({
    required String jalonId,
    required int amountCentimes,
  }) async {
    try {
      final response = await _apiClient.post(
        '/escrow/release/labor',
        data: {
          'jalon_id': jalonId,
          'amount_centimes': amountCentimes,
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

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to release labor funds: ${e.toString()}');
    }
  }

  /// Handle Dio errors
  Exception _handleError(DioException error) {
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return Exception(
        'Connection timeout. Please check your internet connection.',
      );
    }

    if (error.type == DioExceptionType.connectionError) {
      return Exception('Network error. Please check your internet connection.');
    }

    if (error.response != null) {
      final statusCode = error.response!.statusCode;
      final data = error.response!.data;

      if (statusCode == 400) {
        if (data is Map<String, dynamic> && data.containsKey('message')) {
          return Exception(data['message']);
        }
        return Exception('Invalid escrow operation');
      }

      if (statusCode == 401) {
        return Exception('Unauthorized. Please log in again.');
      }

      if (statusCode == 403) {
        return Exception('Access denied. You do not have permission for this operation.');
      }

      if (statusCode == 404) {
        return Exception('Escrow record not found');
      }

      if (statusCode == 422) {
        // Validation error
        if (data is Map<String, dynamic>) {
          if (data.containsKey('message')) {
            return Exception(data['message']);
          }
          if (data.containsKey('errors')) {
            final errors = data['errors'] as Map<String, dynamic>;
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return Exception(firstError.first.toString());
            }
          }
        }
        return Exception('Validation error');
      }

      if (statusCode == 500) {
        return Exception('Server error. Please try again later.');
      }

      if (data is Map<String, dynamic> && data.containsKey('message')) {
        return Exception(data['message']);
      }

      return Exception('Server error: $statusCode');
    }

    return Exception('Unknown error occurred. Please try again.');
  }
}
