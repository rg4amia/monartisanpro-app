import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/devis_repository.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/mission_repository.dart';

class CreateDevisUseCase {
  final DevisRepository _devisRepository;
  final MissionRepository _missionRepository;

  CreateDevisUseCase(this._devisRepository, this._missionRepository);

  Future<Devis> execute({
    required String missionId,
    required List<DevisLine> lineItems,
    DateTime? expiresAt,
  }) async {
    // Validate inputs
    if (lineItems.isEmpty) {
      throw ArgumentError('Devis must have at least one line item');
    }

    // Check if mission exists and can receive more quotes
    final mission = await _missionRepository.getMissionById(missionId);
    if (mission == null) {
      throw ArgumentError('Mission not found');
    }

    if (!mission.canReceiveMoreQuotes) {
      throw ArgumentError('Mission cannot receive more quotes');
    }

    // Calculate amounts
    double materialsAmount = 0;
    double laborAmount = 0;

    for (final line in lineItems) {
      if (line.quantity <= 0) {
        throw ArgumentError('Line item quantity must be greater than 0');
      }
      if (line.unitPrice <= 0) {
        throw ArgumentError('Line item unit price must be greater than 0');
      }

      final lineTotal = line.total;
      if (line.type == DevisLineType.material) {
        materialsAmount += lineTotal;
      } else {
        laborAmount += lineTotal;
      }
    }

    final totalAmount = materialsAmount + laborAmount;

    if (totalAmount <= 0) {
      throw ArgumentError('Total amount must be greater than 0');
    }

    return await _devisRepository.createDevis(
      missionId: missionId,
      totalAmount: totalAmount,
      materialsAmount: materialsAmount,
      laborAmount: laborAmount,
      lineItems: lineItems,
      expiresAt: expiresAt,
    );
  }
}
