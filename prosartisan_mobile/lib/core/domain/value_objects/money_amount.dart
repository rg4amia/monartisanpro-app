/// Value Object representing a monetary amount in XOF (West African CFA franc)
/// Stores amounts in centimes (smallest unit) to avoid floating-point precision issues
class MoneyAmount {
  final int _amountInCentimes;
  final Currency _currency;

  const MoneyAmount._(this._amountInCentimes, this._currency);

  factory MoneyAmount.fromCentimes(int centimes, [Currency? currency]) {
    if (centimes < 0) {
      throw ArgumentError('Money amount cannot be negative');
    }
    return MoneyAmount._(centimes, currency ?? Currency.xof);
  }

  factory MoneyAmount.fromFrancs(double francs, [Currency? currency]) {
    return MoneyAmount._((francs * 100).round(), currency ?? Currency.xof);
  }

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    return MoneyAmount.fromCentimes(
      json['amount_centimes'] as int,
      Currency.fromCode(json['currency'] as String? ?? 'XOF'),
    );
  }

  MoneyAmount add(MoneyAmount other) {
    _assertSameCurrency(other);
    return MoneyAmount._(
      _amountInCentimes + other._amountInCentimes,
      _currency,
    );
  }

  MoneyAmount subtract(MoneyAmount other) {
    _assertSameCurrency(other);
    if (_amountInCentimes < other._amountInCentimes) {
      throw ArgumentError('Cannot subtract to negative amount');
    }
    return MoneyAmount._(
      _amountInCentimes - other._amountInCentimes,
      _currency,
    );
  }

  MoneyAmount multiply(double factor) {
    if (factor < 0) {
      throw ArgumentError('Multiplication factor cannot be negative');
    }
    return MoneyAmount._((_amountInCentimes * factor).round(), _currency);
  }

  MoneyAmount percentage(int percent) {
    if (percent < 0 || percent > 100) {
      throw ArgumentError('Percentage must be between 0 and 100');
    }
    return MoneyAmount._(
      (_amountInCentimes * (percent / 100)).round(),
      _currency,
    );
  }

  bool isGreaterThan(MoneyAmount other) {
    _assertSameCurrency(other);
    return _amountInCentimes > other._amountInCentimes;
  }

  bool isLessThan(MoneyAmount other) {
    _assertSameCurrency(other);
    return _amountInCentimes < other._amountInCentimes;
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is MoneyAmount &&
        other._amountInCentimes == _amountInCentimes &&
        other._currency == _currency;
  }

  @override
  int get hashCode => Object.hash(_amountInCentimes, _currency);

  double toDouble() => _amountInCentimes / 100;

  int toCentimes() => _amountInCentimes;

  /// Format amount according to French locale with thousand separators
  /// Example: 1 000 000 FCFA
  String format() {
    final francs = toDouble();
    final formatted = francs
        .toStringAsFixed(0)
        .replaceAllMapped(
          RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        );
    return '$formatted ${_currency.symbol}';
  }

  Currency get currency => _currency;

  Map<String, dynamic> toJson() {
    return {
      'amount_centimes': _amountInCentimes,
      'amount_francs': toDouble(),
      'currency': _currency.code,
      'formatted': format(),
    };
  }

  void _assertSameCurrency(MoneyAmount other) {
    if (_currency != other._currency) {
      throw ArgumentError('Cannot operate on different currencies');
    }
  }

  @override
  String toString() => format();
}

/// Value Object representing a currency
/// Currently supports only XOF (West African CFA franc)
class Currency {
  final String code;
  final String symbol;

  const Currency._(this.code, this.symbol);

  static const Currency xof = Currency._('XOF', 'FCFA');

  factory Currency.fromCode(String code) {
    if (code != 'XOF') {
      throw ArgumentError(
        'Unsupported currency code: $code. Only XOF is supported.',
      );
    }
    return xof;
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Currency && other.code == code;
  }

  @override
  int get hashCode => code.hashCode;

  @override
  String toString() => code;
}
