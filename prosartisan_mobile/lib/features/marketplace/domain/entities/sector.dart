import 'package:equatable/equatable.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/trade.dart';

class Sector extends Equatable {
  final int id;
  final String name;
  final List<Trade> trades;

  const Sector({required this.id, required this.name, this.trades = const []});

  @override
  List<Object?> get props => [id, name, trades];
}
