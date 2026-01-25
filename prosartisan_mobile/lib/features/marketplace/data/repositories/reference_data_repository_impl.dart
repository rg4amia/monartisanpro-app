import 'package:dio/dio.dart';
import 'package:prosartisan_mobile/core/constants/app_strings.dart';
import 'package:prosartisan_mobile/core/error/failures.dart';
import 'package:prosartisan_mobile/features/marketplace/data/models/sector_model.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/reference_data_repository.dart';

class ReferenceDataRepositoryImpl implements ReferenceDataRepository {
  final Dio client;

  ReferenceDataRepositoryImpl({required this.client});

  @override
  Future<List<SectorModel>> getSectors() async {
    try {
      final response = await client.get(
        '${AppStrings.apiBaseUrl}/reference/trades',
        options: Options(
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        ),
      );

      if (response.statusCode == 200) {
        final data = response.data;
        // Handle if data is wrapped in 'data' key or direct list
        final listData = data is Map ? data['data'] : data;

        return (listData as List)
            .map((e) => SectorModel.fromJson(e as Map<String, dynamic>))
            .toList();
      } else {
        throw const ServerFailure(message: 'Failed to fetch reference data');
      }
    } catch (e) {
      throw ServerFailure(message: e.toString());
    }
  }
}
