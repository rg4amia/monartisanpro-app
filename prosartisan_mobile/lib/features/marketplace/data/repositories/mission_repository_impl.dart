import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/data/datasources/marketplace_remote_datasource.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/mission_repository.dart';

class MissionRepositoryImpl implements MissionRepository {
  final MarketplaceRemoteDataSource _remoteDataSource;

  MissionRepositoryImpl(this._remoteDataSource);

  @override
  Future<List<Mission>> getMissions({
    int page = 1,
    int limit = 20,
    String? clientId,
    MissionStatus? status,
  }) async {
    final models = await _remoteDataSource.getMissions(
      page: page,
      limit: limit,
      clientId: clientId,
      status: status,
    );
    return models.map((model) => model.toEntity()).toList();
  }

  @override
  Future<Mission?> getMissionById(String id) async {
    final model = await _remoteDataSource.getMissionById(id);
    return model?.toEntity();
  }

  @override
  Future<List<Mission>> getMissionsNearLocation(
    GPSCoordinates location,
    double radiusKm, {
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  }) async {
    final models = await _remoteDataSource.getMissionsNearLocation(
      location,
      radiusKm,
      category: category,
      page: page,
      limit: limit,
    );
    return models.map((model) => model.toEntity()).toList();
  }

  @override
  Future<Mission> createMission({
    required String description,
    required TradeCategory category,
    int? tradeId,
    required GPSCoordinates location,
    required double budgetMin,
    required double budgetMax,
  }) async {
    final model = await _remoteDataSource.createMission(
      description: description,
      category: category,
      tradeId: tradeId,
      location: location,
      budgetMin: budgetMin,
      budgetMax: budgetMax,
    );
    return model.toEntity();
  }

  @override
  Future<Mission> updateMission(Mission mission) async {
    // This would typically involve a PUT request to update the mission
    // For now, we'll throw an unimplemented error as it's not in the current task scope
    throw UnimplementedError('Update mission not implemented yet');
  }

  @override
  Future<void> deleteMission(String id) async {
    // This would typically involve a DELETE request
    // For now, we'll throw an unimplemented error as it's not in the current task scope
    throw UnimplementedError('Delete mission not implemented yet');
  }
}
