import 'package:flutter_test/flutter_test.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/money_amount.dart';

void main() {
  group('MoneyAmount', () {
    test('creates money amount from centimes', () {
      final amount = MoneyAmount.fromCentimes(100000);

      expect(amount.toCentimes(), equals(100000));
      expect(amount.toDouble(), equals(1000.0));
    });

    test('creates money amount from francs', () {
      final amount = MoneyAmount.fromFrancs(1000.50);

      expect(amount.toCentimes(), equals(100050));
      expect(amount.toDouble(), equals(1000.50));
    });

    test('rejects negative amounts', () {
      expect(() => MoneyAmount.fromCentimes(-100), throwsArgumentError);
    });

    test('adds two amounts', () {
      final amount1 = MoneyAmount.fromCentimes(100000);
      final amount2 = MoneyAmount.fromCentimes(50000);

      final result = amount1.add(amount2);

      expect(result.toCentimes(), equals(150000));
    });

    test('subtracts two amounts', () {
      final amount1 = MoneyAmount.fromCentimes(100000);
      final amount2 = MoneyAmount.fromCentimes(30000);

      final result = amount1.subtract(amount2);

      expect(result.toCentimes(), equals(70000));
    });

    test('rejects subtraction to negative', () {
      final amount1 = MoneyAmount.fromCentimes(50000);
      final amount2 = MoneyAmount.fromCentimes(100000);

      expect(() => amount1.subtract(amount2), throwsArgumentError);
    });

    test('multiplies amount', () {
      final amount = MoneyAmount.fromCentimes(100000);

      final result = amount.multiply(1.5);

      expect(result.toCentimes(), equals(150000));
    });

    test('calculates percentage', () {
      final amount = MoneyAmount.fromCentimes(100000);

      final result = amount.percentage(65);

      expect(result.toCentimes(), equals(65000));
    });

    test('rejects invalid percentage', () {
      final amount = MoneyAmount.fromCentimes(100000);

      expect(() => amount.percentage(150), throwsArgumentError);
    });

    test('compares amounts', () {
      final amount1 = MoneyAmount.fromCentimes(100000);
      final amount2 = MoneyAmount.fromCentimes(50000);
      final amount3 = MoneyAmount.fromCentimes(100000);

      expect(amount1.isGreaterThan(amount2), isTrue);
      expect(amount2.isLessThan(amount1), isTrue);
      expect(amount1, equals(amount3));
    });

    test('formats amount in French locale', () {
      final amount = MoneyAmount.fromCentimes(100000000); // 1,000,000 francs

      final formatted = amount.format();

      expect(formatted, equals('1 000 000 FCFA'));
    });

    test('converts to JSON', () {
      final amount = MoneyAmount.fromCentimes(100000);

      final json = amount.toJson();

      expect(json['amount_centimes'], equals(100000));
      expect(json['amount_francs'], equals(1000.0));
      expect(json['currency'], equals('XOF'));
      expect(json['formatted'], isNotNull);
    });

    test('creates from JSON', () {
      final json = {'amount_centimes': 100000, 'currency': 'XOF'};

      final amount = MoneyAmount.fromJson(json);

      expect(amount.toCentimes(), equals(100000));
      expect(amount.currency.code, equals('XOF'));
    });
  });

  group('Currency', () {
    test('creates XOF currency', () {
      final currency = Currency.xof;

      expect(currency.code, equals('XOF'));
      expect(currency.symbol, equals('FCFA'));
    });

    test('creates from code', () {
      final currency = Currency.fromCode('XOF');

      expect(currency, equals(Currency.xof));
    });

    test('rejects unsupported currency', () {
      expect(() => Currency.fromCode('USD'), throwsArgumentError);
    });
  });
}
