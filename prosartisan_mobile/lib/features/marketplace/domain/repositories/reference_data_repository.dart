import 'package:prosartisan_mobile/features/marketplace/domain/entities/sector.dart';

abstract class ReferenceDataRepository {
  Future<List<Sector>> getSectors();
}
