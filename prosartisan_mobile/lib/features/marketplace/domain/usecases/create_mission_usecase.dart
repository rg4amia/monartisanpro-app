import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/mission_repository.dart';

class CreateMissionUseCase {
  final MissionRepository _repository;

  CreateMissionUseCase(this._repository);

  Future<Mission> execute({
    required String description,
    required TradeCategory category,
    int? tradeId,
    required GPSCoordinates location,
    required double budgetMin,
    required double budgetMax,
  }) async {
    // Validate inputs
    if (description.trim().isEmpty) {
      throw ArgumentError('Description cannot be empty');
    }

    if (budgetMin <= 0) {
      throw ArgumentError('Budget minimum must be greater than 0');
    }

    if (budgetMax <= budgetMin) {
      throw ArgumentError('Budget maximum must be greater than minimum');
    }

    if (!location.hasAcceptableAccuracy) {
      throw ArgumentError(
        'GPS accuracy is not acceptable for mission creation',
      );
    }

    return await _repository.createMission(
      description: description.trim(),
      category: category,
      tradeId: tradeId,
      location: location,
      budgetMin: budgetMin,
      budgetMax: budgetMax,
    );
  }
}
