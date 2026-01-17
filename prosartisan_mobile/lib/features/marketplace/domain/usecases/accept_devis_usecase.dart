import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/devis_repository.dart';

class AcceptDevisUseCase {
  final DevisRepository _repository;

  AcceptDevisUseCase(this._repository);

  Future<Devis> execute(String devisId) async {
    // Get the devis to validate it exists and is in correct state
    final devis = await _repository.getDevisById(devisId);
    if (devis == null) {
      throw ArgumentError('Devis not found');
    }

    if (devis.status != DevisStatus.pending) {
      throw ArgumentError('Only pending devis can be accepted');
    }

    if (devis.isExpired) {
      throw ArgumentError('Cannot accept expired devis');
    }

    return await _repository.acceptDevis(devisId);
  }
}
