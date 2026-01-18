<?php

return [
 /*
    |--------------------------------------------------------------------------
    | Reputation Language Lines
    |--------------------------------------------------------------------------
    */

 'score_updated' => 'Score N\'Zassa mis à jour : :score/100',
 'score_calculation_failed' => 'Échec du calcul du score de réputation.',
 'rating_submitted' => 'Évaluation soumise avec succès.',
 'rating_updated' => 'Évaluation mise à jour avec succès.',
 'rating_invalid' => 'Évaluation invalide. Doit être entre 1 et 5 étoiles.',
 'micro_credit_eligible' => 'Félicitations ! Vous êtes éligible au micro-crédit (Score > 700).',
 'micro_credit_not_eligible' => 'Score insuffisant pour le micro-crédit (Score requis > 700).',
 'profile_not_found' => 'Profil de réputation non trouvé.',
 'metrics_aggregated' => 'Métriques de réputation agrégées avec succès.',

 'components' => [
  'reliability' => 'Fiabilité',
  'integrity' => 'Intégrité',
  'quality' => 'Qualité',
  'reactivity' => 'Réactivité',
 ],

 'weights' => [
  'reliability' => '40%',
  'integrity' => '30%',
  'quality' => '20%',
  'reactivity' => '10%',
 ],

 'score_ranges' => [
  'excellent' => 'Excellent (80-100)',
  'good' => 'Bon (60-79)',
  'average' => 'Moyen (40-59)',
  'poor' => 'Faible (20-39)',
  'very_poor' => 'Très faible (0-19)',
 ],
];
