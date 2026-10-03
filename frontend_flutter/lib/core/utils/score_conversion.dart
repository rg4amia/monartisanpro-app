/// Conversion d'une note moyenne (1 à 5 étoiles) en points d'un pilier du
/// Score ProsArtisan, identique à celle du serveur (`ScoreService::pillarPoints`) :
/// une étoile vaut 0 point, cinq étoiles le maximum du pilier. Une valeur
/// absente ou illisible vaut 0.
double pillarPointsFromRating(dynamic average, num maxPoints) {
  final parsed = average is num
      ? average.toDouble()
      : double.tryParse(average?.toString() ?? '') ?? 0;

  if (parsed <= 1) return 0;

  return ((parsed - 1) / 4 * maxPoints).clamp(0, maxPoints).toDouble();
}
