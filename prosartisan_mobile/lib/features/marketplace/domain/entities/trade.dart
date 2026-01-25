import 'package:equatable/equatable.dart';

class Trade extends Equatable {
  final int id;
  final String name;
  final int sectorId;

  const Trade({required this.id, required this.name, required this.sectorId});

  @override
  List<Object?> get props => [id, name, sectorId];
}
