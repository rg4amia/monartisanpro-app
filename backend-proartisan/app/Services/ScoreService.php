<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScoreService
{
    /**
     * Poids des événements du Score ProsArtisan (échelle 0–1000, base 300).
     */
    private const EVENT_POINTS = [
        'success_mission'       =>   5,
        'jalon_on_time'         =>   2,
        'jalon_delay'           => -15,
        'dispute_fraud'         => -150,
        'dispute_abandon'       => -300,
        'evaluation_negative'   => -15,
        'inactivity_decay'      =>  -5,

        // --- Événements Logistiques (Fournisseurs & Livreurs) ---
        'jcode_scan_success'         =>   5,
        'rupture_stock_non_signalee' => -20,
        'fraude_gps_tentative'       => -50,
        'livraison_on_time'          =>   5,
        'livraison_retard'           => -15,
        'casse_materiel'             => -100,
    ];

    private const BASE_SCORE = 0;
    private const MIN_SCORE  = 0;
    private const MAX_SCORE  = 1000;

    // ──────────────────────────────────────────────
    //  Recalcul principal (appelé après évaluations)
    // ──────────────────────────────────────────────

    /**
     * Recalcule le Score ProsArtisan d'un artisan à partir de la dernière évaluation
     * reçue et de l'ensemble du Ledger.
     */
    public function recalculate(User $artisan): int
    {
        if ($artisan->score_frozen) {
            return $artisan->score_prosartisan;
        }

        $lastEvaluation = Evaluation::where('evalue_id', $artisan->id)->latest('id')->first();
        if (!$lastEvaluation) {
            return $artisan->score_prosartisan;
        }

        $credibility = $this->resolveCredibility($lastEvaluation->evaluateur);

        // Fiabilité 40% + Intégrité 30% + Qualité 20% + Réactivité 10%
        $avgScore = (
            $lastEvaluation->fiabilite * 0.40 +
            $lastEvaluation->integrite * 0.30 +
            $lastEvaluation->qualite * 0.20 +
            $lastEvaluation->reactivite * 0.10
        );

        $points = 0;
        $eventType = 'evaluation';
        $desc = "Évaluation reçue pour la mission #{$lastEvaluation->mission_id}";

        if ($avgScore >= 4.0) {
            $points = self::EVENT_POINTS['success_mission'];
            $eventType = 'success_mission';
        } elseif ($avgScore < 2.0) {
            $points = self::EVENT_POINTS['evaluation_negative'];
            $eventType = 'evaluation_negative';
        }

        if ($points !== 0) {
            ScoreLedgerEntry::create([
                'user_id'            => $artisan->id,
                'event_type'         => $eventType,
                'points'             => $points,
                'credibility_factor' => $credibility,
                'evaluation_id'      => $lastEvaluation->id,
                'mission_id'         => $lastEvaluation->mission_id,
                'description'        => $desc,
            ]);
        }

        return $this->recalculateFromLedger($artisan);
    }

    /**
     * Recalcule le Score de Fluidité (Logistique) d'un fournisseur ou d'un livreur
     * à partir de la dernière évaluation manuelle reçue.
     * Pour la logistique, la fiabilité (ponctualité/stock) et la qualité (état matériel)
     * sont prédominantes.
     */
    public function recalculateLogistic(User $logisticWorker): int
    {
        if ($logisticWorker->score_frozen) {
            return $logisticWorker->score_prosartisan;
        }

        $lastEvaluation = Evaluation::where('evalue_id', $logisticWorker->id)->latest('id')->first();
        if (!$lastEvaluation) {
            return $logisticWorker->score_prosartisan;
        }

        $credibility = $this->resolveCredibility($lastEvaluation->evaluateur);

        // Pour la logistique : Fiabilité/Ponctualité (50%) + Qualité (30%) + Réactivité (20%)
        // L'intégrité est gérée de manière stricte par les règles métier (fraudes GPS).
        $avgScore = (
            $lastEvaluation->fiabilite * 0.50 +
            $lastEvaluation->qualite * 0.30 +
            $lastEvaluation->reactivite * 0.20
        );

        $points = 0;
        $eventType = 'evaluation';
        $desc = $lastEvaluation->mission_id
            ? "Évaluation de prestation (mission #{$lastEvaluation->mission_id})"
            : "Évaluation de prestation (commande #{$lastEvaluation->order_id})";

        if ($avgScore >= 4.0) {
            $points = self::EVENT_POINTS['success_mission'];
            $eventType = 'success_mission';
        } elseif ($avgScore < 2.0) {
            $points = self::EVENT_POINTS['evaluation_negative'];
            $eventType = 'evaluation_negative';
        }

        if ($points !== 0) {
            ScoreLedgerEntry::create([
                'user_id'            => $logisticWorker->id,
                'event_type'         => $eventType,
                'points'             => $points,
                'credibility_factor' => $credibility,
                'evaluation_id'      => $lastEvaluation->id,
                'mission_id'         => $lastEvaluation->mission_id,
                'order_id'           => $lastEvaluation->order_id,
                'description'        => $desc,
            ]);
        }

        return $this->recalculateFromLedger($logisticWorker);
    }

    // ──────────────────────────────────────────────
    //  Méthodes événementielles spécifiques
    // ──────────────────────────────────────────────

    /**
     * Enregistre un événement générique dans le Ledger et recalcule.
     */
    public function recordEvent(
        User $artisan,
        string $eventType,
        ?int $missionId = null,
        ?int $evaluationId = null,
        ?string $description = null,
        float $credibilityFactor = 1.00,
    ): int {
        $points = self::EVENT_POINTS[$eventType] ?? 0;
        if ($points === 0) {
            return $artisan->score_prosartisan;
        }

        ScoreLedgerEntry::create([
            'user_id'            => $artisan->id,
            'event_type'         => $eventType,
            'points'             => $points,
            'credibility_factor' => $credibilityFactor,
            'evaluation_id'      => $evaluationId,
            'mission_id'         => $missionId,
            'description'        => $description ?? "Événement: {$eventType}",
        ]);

        return $this->recalculateFromLedger($artisan);
    }

    /**
     * Jalon soumis dans les temps → +2 pts.
     */
    public function recordJalonOnTime(User $artisan, int $missionId): int
    {
        return $this->recordEvent(
            $artisan,
            'jalon_on_time',
            $missionId,
            description: "Jalon soumis dans les temps (mission #{$missionId})",
        );
    }

    /**
     * Retard de jalon > 48h → −15 pts.
     */
    public function recordJalonDelay(User $artisan, int $missionId): int
    {
        return $this->recordEvent(
            $artisan,
            'jalon_delay',
            $missionId,
            description: "Retard de jalon > 48h (mission #{$missionId})",
        );
    }

    // ──────────────────────────────────────────────
    //  Méthodes Logistiques (Fournisseurs & Livreurs)
    // ──────────────────────────────────────────────

    public function recordJCodeSuccess(User $fournisseur, int $missionId, string $jcodeCode): int
    {
        return $this->recordEvent(
            $fournisseur,
            'jcode_scan_success',
            $missionId,
            description: "J-Code scanné avec succès : {$jcodeCode}",
        );
    }

    public function recordGpsFraudAttempt(User $fournisseur, ?int $missionId = null, ?string $jcodeCode = null): int
    {
        return $this->recordEvent(
            $fournisseur,
            'fraude_gps_tentative',
            $missionId,
            description: "Tentative de validation J-Code hors zone GPS (> 100m) " . ($jcodeCode ? "[{$jcodeCode}]" : ""),
        );
    }

    public function recordDeliveryOnTime(User $livreur, int $missionId): int
    {
        return $this->recordEvent(
            $livreur,
            'livraison_on_time',
            $missionId,
            description: "Livraison ponctuelle validée (mission #{$missionId})",
        );
    }

    // ──────────────────────────────────────────────
    //  Dégradation temporelle (« La Rouille »)
    // ──────────────────────────────────────────────

    /**
     * Applique la pénalité d'inactivité pour un artisan inactif ≥ 60 jours.
     * Retourne le nombre de points retirés (positif) ou 0 si non-éligible.
     */
    public function applyInactivityDecay(User $artisan): int
    {
        $inactivityDays = $this->getInactivityDays($artisan);

        if ($inactivityDays < 60) {
            return 0;
        }

        // -5 pts par semaine d'inactivité au-delà de 60 jours
        $weeksOver = (int) floor(($inactivityDays - 60) / 7);
        if ($weeksOver < 1) {
            $weeksOver = 1; // au moins 1 semaine de pénalité à ≥ 60 jours
        }

        $totalPenalty = self::EVENT_POINTS['inactivity_decay'] * $weeksOver;

        ScoreLedgerEntry::create([
            'user_id'            => $artisan->id,
            'event_type'         => 'inactivity_decay',
            'points'             => $totalPenalty,
            'credibility_factor' => 1.00,
            'description'        => "Dégradation inactivité: {$inactivityDays}j ({$weeksOver} sem. au-delà de 60j)",
        ]);

        $this->recalculateFromLedger($artisan);

        return abs($totalPenalty);
    }

    /**
     * Nombre de jours depuis la dernière activité significative.
     */
    public function getInactivityDays(User $artisan): int
    {
        $lastJalonValidated = DB::table('jalons')
            ->join('missions', 'jalons.mission_id', '=', 'missions.id')
            ->where('missions.artisan_id', $artisan->id)
            ->where('jalons.statut', 'valide')
            ->max('jalons.updated_at');

        $lastMissionAccepted = Mission::where('artisan_id', $artisan->id)
            ->whereNotNull('accepted_at')
            ->max('accepted_at');

        $lastActivity = collect([$lastJalonValidated, $lastMissionAccepted])
            ->filter()
            ->map(fn($d) => \Carbon\Carbon::parse($d))
            ->max();

        if (!$lastActivity) {
            return (int) now()->diffInDays($artisan->created_at);
        }

        return (int) now()->diffInDays($lastActivity);
    }

    // ──────────────────────────────────────────────
    //  Recalcul pur à partir du Ledger
    /**
     * Somme les piliers d'évaluation pondérés par la maturité (10 missions) et le Ledger pour score_prosartisan (0 à 1000).
     */
    public function recalculateFromLedger(User $artisan): int
    {
        if ($artisan->score_frozen) {
            return $artisan->score_prosartisan;
        }

        $row = DB::selectOne("
            SELECT
                AVG(fiabilite)  AS avg_fiabilite,
                AVG(integrite)  AS avg_integrite,
                AVG(qualite)    AS avg_qualite,
                AVG(reactivite) AS avg_reactivite,
                COUNT(*)        AS total_evaluations
            FROM evaluations
            WHERE evalue_id = ?
        ", [$artisan->id]);

        $evalScoreBase = 0;
        $totalEvals = (int) ($row?->total_evaluations ?? 0);

        if ($row && $totalEvals > 0) {
            $f = (float) ($row->avg_fiabilite ?? 0);
            $i = (float) ($row->avg_integrite ?? 0);
            $q = (float) ($row->avg_qualite ?? 0);
            $r = (float) ($row->avg_reactivite ?? 0);

            // Somme brute des 4 piliers : Fiabilité (400) + Intégrité (300) + Qualité (200) + Réactivité (100)
            $rawCriteriaScore = ($f / 5.0 * 400) + ($i / 5.0 * 300) + ($q / 5.0 * 200) + ($r / 5.0 * 100);

            // Condition d'excellence : Au moins 3 critères avec moyenne >= 4.8 / 5 pour dépasser 800
            $countExcellence = 0;
            if ($f >= 4.8) $countExcellence++;
            if ($i >= 4.8) $countExcellence++;
            if ($q >= 4.8) $countExcellence++;
            if ($r >= 4.8) $countExcellence++;

            if ($countExcellence < 3) {
                $rawCriteriaScore = min(800, $rawCriteriaScore);
            }

            // Facteur de maturité : 10 missions nécessaires pour débloquer 100% du score potentiel
            $volumeFactor = min(1.0, $totalEvals / 10.0);
            $evalScoreBase = (int) round($rawCriteriaScore * $volumeFactor);
        }

        $ledgerEntries = ScoreLedgerEntry::where('user_id', $artisan->id)->get();

        if ($totalEvals === 0 && $ledgerEntries->isEmpty()) {
            return (int) $artisan->score_prosartisan;
        }

        $ledgerSum = (int) $ledgerEntries->sum(function ($entry) {
            return $entry->points * $entry->credibility_factor;
        });

        $newScore = min(self::MAX_SCORE, max(self::MIN_SCORE, $evalScoreBase + $ledgerSum));
        $artisan->update(['score_prosartisan' => $newScore]);

        return $newScore;
    }

    // ──────────────────────────────────────────────
    //  Indice de Crédibilité Ck
    // ──────────────────────────────────────────────

    /**
     * Résout l'indice de crédibilité d'un évaluateur.
     *   - Nouveau client (0 chantier) : 0.1
     *   - Client KYC actif + > 3 chantiers : 1.0
     *   - Client B2B (institutionnel) : 1.5
     */
    public function resolveCredibility(?User $evaluateur): float
    {
        if (!$evaluateur) {
            return 0.1;
        }

        if ($evaluateur->role === 'client_b2b') {
            return 1.5;
        }

        if ($evaluateur->isKycActif()) {
            $completedMissionsCount = Mission::where('client_id', $evaluateur->id)
                ->where('status', \App\States\Mission\CompletedState::class)
                ->count();
            if ($completedMissionsCount > 3) {
                return 1.0;
            }
        }

        return 0.1;
    }

    // ──────────────────────────────────────────────
    //  Détail & éligibilité
    // ──────────────────────────────────────────────

    /**
     * Retourne le détail du score et les métriques de maturité.
     */
    public function getScoreDetail(User $artisan): array
    {
        $calculatedScore = $this->recalculateFromLedger($artisan);

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

        $threshold = config('prosartisan.score_prosartisan.credit_threshold', 70);
        $totalEvals = (int) ($row?->total_evaluations ?? 0);

        return [
            'score_prosartisan'          => $calculatedScore,
            'micro_credit_eligible'      => $calculatedScore >= $threshold,
            'total_evaluations'          => $totalEvals,
            'maturity_missions_target'   => 10,
            'maturity_missions_count'    => min(10, $totalEvals),
            'maturity_percentage'        => round(min(1.0, $totalEvals / 10.0) * 100, 1),
            'breakdown'                  => [
                'fiabilite'  => round((float) ($row?->avg_fiabilite ?? 0), 1),
                'integrite'  => round((float) ($row?->avg_integrite ?? 0), 1),
                'qualite'    => round((float) ($row?->avg_qualite ?? 0), 1),
                'reactivite' => round((float) ($row?->avg_reactivite ?? 0), 1),
            ],
            'average_rating'             => round((float) ($row?->avg_note ?? 0), 1),
        ];
    }

    public function isEligibleCredit(User $artisan): bool
    {
        return $artisan->score_prosartisan >= config('prosartisan.score_prosartisan.credit_threshold', 70);
    }
}
