import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';

abstract class DevisRepository {
  Future<List<Devis>> getDevisByMissionId(String missionId);

  Future<List<Devis>> getDevisByArtisanId(String artisanId);

  Future<Devis?> getDevisById(String id);

  Future<Devis> createDevis({
    required String missionId,
    required double totalAmount,
    required double materialsAmount,
    required double laborAmount,
    required List<DevisLine> lineItems,
    DateTime? expiresAt,
  });

  Future<Devis> acceptDevis(String devisId);

  Future<Devis> rejectDevis(String devisId);

  Future<void> deleteDevis(String id);
}
