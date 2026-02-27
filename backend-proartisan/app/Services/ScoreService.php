<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScoreService
{
    /**
     * Recalcule le Score N'Zassa d'un artisan.
     * Fiabilité 40% + Intégrité 30% + Qualité 20% + Réactivité 10%.
     */
    public function recalculate(User $artisan): int
    {
        $weights = config('prosartisan.score_nzassa.weights', [
            'fiabilite'  => 40,
            'integrite'  => 30,
            'qualite'    => 20,
            'reactivite' => 10,
        ]);

        $row = DB::selectOne("
            SELECT
                AVG(fiabilite)  AS avg_fiabilite,
                AVG(integrite)  AS avg_integrite,
                AVG(qualite)    AS avg_qualite,
                AVG(reactivite) AS avg_reactivite,
                AVG(note)       AS avg_note,
                COUNT(*)        AS total
            FROM evaluations
            WHERE evalue_id = ?
        ", [$artisan->id]);

        if (! $row || $row->total == 0) {
            return $artisan->score_nzassa;
        }

        $score = (
            ($row->avg_fiabilite  ?? 3) * $weights['fiabilite'] +
            ($row->avg_integrite  ?? 3) * $weights['integrite'] +
            ($row->avg_qualite    ?? 3) * $weights['qualite'] +
            ($row->avg_reactivite ?? 3) * $weights['reactivite']
        ) / (5 * 100); // normalise 0-100

        $score = (int) min(100, max(0, round($score * 100)));

        $artisan->update(['score_nzassa' => $score]);

        return $score;
    }

    /**
     * Retourne le détail du score.
     */
    public function getScoreDetail(User $artisan): array
    {
        $row = DB::selectOne("
            SELECT
                AVG(fiabilite)  AS avg_fiabilite,
                AVG(integrite)  AS avg_integrite,
                AVG(qualite)    AS avg_qualite,
                AVG(reactivite) AS avg_reactivite,
                AVG(note)       AS avg_note,
                COUNT(*)        AS total_evaluations
            FROM evaluations
            WHERE evalue_id = ?
        ", [$artisan->id]);

        $threshold = config('prosartisan.score_nzassa.credit_threshold', 70);

        return [
            'score_nzassa'          => $artisan->score_nzassa,
            'micro_credit_eligible' => $artisan->score_nzassa >= $threshold,
            'total_evaluations'     => $row?->total_evaluations ?? 0,
            'breakdown'             => [
                'fiabilite'  => round((float) ($row?->avg_fiabilite ?? 0), 1),
                'integrite'  => round((float) ($row?->avg_integrite ?? 0), 1),
                'qualite'    => round((float) ($row?->avg_qualite ?? 0), 1),
                'reactivite' => round((float) ($row?->avg_reactivite ?? 0), 1),
            ],
            'average_rating' => round((float) ($row?->avg_note ?? 0), 1),
        ];
    }

    public function isEligibleCredit(User $artisan): bool
    {
        return $artisan->score_nzassa >= config('prosartisan.score_nzassa.credit_threshold', 70);
    }
}
