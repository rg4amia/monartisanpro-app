import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

abstract class MissionRepository {
  Future<List<Mission>> getMissions({
    int page = 1,
    int limit = 20,
    String? clientId,
    MissionStatus? status,
  });

  Future<Mission?> getMissionById(String id);

  Future<List<Mission>> getMissionsNearLocation(
    GPSCoordinates location,
    double radiusKm, {
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  });

  Future<Mission> createMission({
    required String description,
    required TradeCategory category,
    required GPSCoordinates location,
    required double budgetMin,
    required double budgetMax,
  });

  Future<Mission> updateMission(Mission mission);

  Future<void> deleteMission(String id);
}
