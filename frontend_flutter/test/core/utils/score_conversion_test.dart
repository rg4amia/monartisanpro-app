import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/utils/score_conversion.dart';

void main() {
  test('une étoile vaut 0 point, cinq étoiles le maximum du pilier', () {
    expect(pillarPointsFromRating(1, 400), 0);
    expect(pillarPointsFromRating(2, 400), 100);
    expect(pillarPointsFromRating(3, 400), 200);
    expect(pillarPointsFromRating(4.5, 200), 175);
    expect(pillarPointsFromRating(5, 400), 400);
  });

  test('une note absente, nulle ou illisible vaut 0, sans exception', () {
    expect(pillarPointsFromRating(null, 300), 0);
    expect(pillarPointsFromRating(0, 300), 0);
    expect(pillarPointsFromRating('abc', 300), 0);
  });

  test('une note reçue en texte ou hors échelle reste bornée', () {
    expect(pillarPointsFromRating('4', 100), 75);
    expect(pillarPointsFromRating(7, 100), 100);
  });
}
