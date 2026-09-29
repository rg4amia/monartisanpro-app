import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';
import '../models/artisan_availability_model.dart';

/// Disponibilité de l'artisan pour l'annuaire du site vitrine (Chantier 15).
///
/// Sans cache : l'artisan doit voir l'état réel de sa demande (en attente,
/// validée, refusée), et une panne s'affiche comme telle (Règle d'or 75).
class ArtisanAvailabilityRepository {
  ArtisanAvailabilityRepository({ApiClient? client})
      : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<AvailabilityOverview> getOverview() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.artisanAvailability),
    );
    return AvailabilityOverview.fromJson(readMap(readMap(res.data)?['data']));
  }

  /// Dépose une déclaration ; elle attend la validation d'un administrateur.
  Future<AvailabilityOverview> submit({
    required String status,
    DateTime? untilDate,
    required List<AvailabilitySlot> slots,
    required bool nightWork,
  }) async {
    final res = await _client.post(
      ApiEndpoints.artisanAvailability,
      data: {
        'status': status,
        'until_date': status == 'disponible' || untilDate == null
            ? null
            : '${untilDate.year.toString().padLeft(4, '0')}-${untilDate.month.toString().padLeft(2, '0')}-${untilDate.day.toString().padLeft(2, '0')}',
        'schedule': slots.map((slot) => slot.toJson()).toList(),
        'night_work': nightWork,
      },
    );
    return AvailabilityOverview.fromJson(readMap(readMap(res.data)?['data']));
  }
}
