import 'package:prosartisan_mobile/features/marketplace/data/datasources/marketplace_remote_datasource.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/devis_repository.dart';

class DevisRepositoryImpl implements DevisRepository {
  final MarketplaceRemoteDataSource _remoteDataSource;

  DevisRepositoryImpl(this._remoteDataSource);

  @override
  Future<List<Devis>> getDevisByMissionId(String missionId) async {
    final models = await _remoteDataSource.getDevisByMissionId(missionId);
    return models.map((model) => model.toEntity()).toList();
  }

  @override
  Future<List<Devis>> getDevisByArtisanId(String artisanId) async {
    final models = await _remoteDataSource.getDevisByArtisanId(artisanId);
    return models.map((model) => model.toEntity()).toList();
  }

  @override
  Future<Devis?> getDevisById(String id) async {
    final model = await _remoteDataSource.getDevisById(id);
    return model?.toEntity();
  }

  @override
  Future<Devis> createDevis({
    required String missionId,
    required double totalAmount,
    required double materialsAmount,
    required double laborAmount,
    required List<DevisLine> lineItems,
    DateTime? expiresAt,
  }) async {
    final model = await _remoteDataSource.createDevis(
      missionId: missionId,
      totalAmount: totalAmount,
      materialsAmount: materialsAmount,
      laborAmount: laborAmount,
      lineItems: lineItems,
      expiresAt: expiresAt,
    );
    return model.toEntity();
  }

  @override
  Future<Devis> acceptDevis(String devisId) async {
    final model = await _remoteDataSource.acceptDevis(devisId);
    return model.toEntity();
  }

  @override
  Future<Devis> rejectDevis(String devisId) async {
    final model = await _remoteDataSource.rejectDevis(devisId);
    return model.toEntity();
  }

  @override
  Future<void> deleteDevis(String id) async {
    // This would typically involve a DELETE request
    // For now, we'll throw an unimplemented error as it's not in the current task scope
    throw UnimplementedError('Delete devis not implemented yet');
  }
}
