<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\ScoreLedgerEntry;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScoreService
{
    /**
     * Poids des événements du Score ProsArtisan (échelle 0–1000, base 0).
     */
    private const EVENT_POINTS = [
        'success_mission' => 5,
        'jalon_on_time' => 2,
        'jalon_delay' => -15,
        'dispute_fraud' => -150,
        'dispute_abandon' => -300,
        'evaluation_negative' => -15,
        'inactivity_decay' => -5,

        // --- Événements Logistiques (Fournisseurs & Livreurs) ---
        'jcode_scan_success' => 5,
        'rupture_stock_non_signalee' => -20,
        'fraude_gps_tentative' => -50,
        'livraison_on_time' => 5,
        'livraison_retard' => -15,
        'casse_materiel' => -100,
    ];

    /** Libellés français des événements du score (Règle d'or 27). */
    private const EVENT_LABELS = [
        'success_mission' => 'Évaluation favorable',
        'evaluation_negative' => 'Évaluation défavorable',
        'evaluation' => 'Évaluation',
        'jalon_on_time' => 'Étape livrée à temps',
        'jalon_delay' => "Retard d'étape",
        'dispute_fraud' => 'Litige perdu ou fraude confirmée',
        'dispute_abandon' => 'Litiges perdus à répétition',
        'inactivity_decay' => 'Inactivité',
        'jcode_scan_success' => 'Bon matériel validé',
        'rupture_stock_non_signalee' => 'Rupture de stock non signalée',
        'fraude_gps_tentative' => 'Validation hors zone GPS',
        'livraison_on_time' => 'Livraison ponctuelle',
        'livraison_retard' => 'Course retirée pour retard',
        'casse_materiel' => 'Matériel endommagé',
    ];

    public static function eventLabel(string $eventType): string
    {
        return self::EVENT_LABELS[$eventType] ?? 'Autre événement';
    }

    private const BASE_SCORE = 0;

    private const MIN_SCORE = 0;

    private const MAX_SCORE = 1000;

    // ──────────────────────────────────────────────
    //  Recalcul principal (appelé après évaluations)
    // ──────────────────────────────────────────────

    /**
     * Recalcule le Score ProsArtisan d'un artisan à partir de la dernière évaluation
     * reçue et de l'ensemble du Ledger.
     */
    public function recalculate(User $artisan, ?Evaluation $evaluation = null): int
    {
        if ($artisan->score_frozen) {
            return $artisan->score_prosartisan;
        }

        // L'évaluation qui vient d'être enregistrée ; à défaut, la dernière reçue.
        $lastEvaluation = $evaluation ?? Evaluation::where('evalue_id', $artisan->id)->latest('id')->first();
        if (! $lastEvaluation) {
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

        if ($points !== 0 && ! $this->alreadyRewarded($lastEvaluation)) {
            ScoreLedgerEntry::create([
                'user_id' => $artisan->id,
                'event_type' => $eventType,
                'points' => $points,
                'credibility_factor' => $credibility,
                'evaluation_id' => $lastEvaluation->id,
                'mission_id' => $lastEvaluation->mission_id,
                'description' => $desc,
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
    public function recalculateLogistic(User $logisticWorker, ?Evaluation $evaluation = null): int
    {
        if ($logisticWorker->score_frozen) {
            return $logisticWorker->score_prosartisan;
        }

        $lastEvaluation = $evaluation ?? Evaluation::where('evalue_id', $logisticWorker->id)->latest('id')->first();
        if (! $lastEvaluation) {
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

        if ($points !== 0 && ! $this->alreadyRewarded($lastEvaluation)) {
            ScoreLedgerEntry::create([
                'user_id' => $logisticWorker->id,
                'event_type' => $eventType,
                'points' => $points,
                'credibility_factor' => $credibility,
                'evaluation_id' => $lastEvaluation->id,
                'mission_id' => $lastEvaluation->mission_id,
                'order_id' => $lastEvaluation->order_id,
                'description' => $desc,
            ]);
        }

        return $this->recalculateFromLedger($logisticWorker);
    }

    /** Le bonus ou le malus d'une évaluation n'est inscrit qu'une fois. */
    private function alreadyRewarded(Evaluation $evaluation): bool
    {
        return ScoreLedgerEntry::where('evaluation_id', $evaluation->id)->exists();
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
            'user_id' => $artisan->id,
            'event_type' => $eventType,
            'points' => $points,
            'credibility_factor' => $credibilityFactor,
            'evaluation_id' => $evaluationId,
            'mission_id' => $missionId,
            'description' => $description ?? "Événement: {$eventType}",
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
            description: 'Tentative de validation J-Code hors zone GPS (> 100m) '.($jcodeCode ? "[{$jcodeCode}]" : ''),
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

    public const INACTIVITY_THRESHOLD_DAYS = 60;

    /** Réglage du backoffice ; tant qu'il n'existe pas, la configuration fait foi. */
    public const INACTIVITY_DECAY_SETTING = 'score_inactivity_decay_enabled';

    /**
     * La dégradation d'inactivité n'agit que si elle est activée : par le
     * réglage du backoffice s'il a été posé, sinon par la configuration.
     */
    public static function inactivityDecayEnabled(): bool
    {
        $setting = Setting::getValueByKey(self::INACTIVITY_DECAY_SETTING);

        return $setting !== null
            ? (bool) $setting
            : (bool) config('prosartisan.score_prosartisan.inactivity_decay_enabled', false);
    }

    /** Points retirés à chaque pénalité d'inactivité. */
    public static function inactivityDecayPoints(): int
    {
        return abs(self::EVENT_POINTS['inactivity_decay']);
    }

    /**
     * Nombre d'artisans que la dégradation viserait aujourd'hui : compte actif,
     * score positif et non gelé, inactif depuis le seuil. Calculé en trois
     * requêtes, quel que soit le nombre d'artisans.
     */
    public function countInactiveArtisans(): int
    {
        $lastJalons = DB::table('jalons')
            ->join('missions', 'jalons.mission_id', '=', 'missions.id')
            ->whereNotNull('missions.artisan_id')
            ->whereIn('jalons.statut', ['valide', 'paye'])
            ->groupBy('missions.artisan_id')
            ->selectRaw('missions.artisan_id as artisan_id, MAX(jalons.updated_at) as derniere')
            ->pluck('derniere', 'artisan_id');

        $lastMissions = DB::table('missions')
            ->whereNotNull('artisan_id')
            ->groupBy('artisan_id')
            ->selectRaw('artisan_id, MAX(updated_at) as derniere')
            ->pluck('derniere', 'artisan_id');

        $limit = now()->subDays(self::INACTIVITY_THRESHOLD_DAYS);
        $count = 0;

        $artisans = DB::table('users')
            ->where('role', 'artisan')
            ->where('account_status', 'actif')
            ->where('score_prosartisan', '>', 0)
            ->where('score_frozen', false)
            ->whereNull('deleted_at')
            ->select(['id', 'created_at'])
            ->orderBy('id');

        foreach ($artisans->lazy() as $artisan) {
            $last = collect([$lastJalons[$artisan->id] ?? null, $lastMissions[$artisan->id] ?? null])
                ->filter()
                ->map(fn ($date) => Carbon::parse($date))
                ->max() ?? Carbon::parse($artisan->created_at);

            if ($last->lte($limit)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retire 5 points à un artisan inactif depuis 60 jours au moins, une fois
     * par semaine au plus, quel que soit le rythme d'appel. Un score déjà nul
     * n'est pas pénalisé : aucune dette ne s'accumule dans le ledger.
     * Retourne le nombre de points retirés, ou 0.
     */
    public function applyInactivityDecay(User $artisan): int
    {
        if ($artisan->score_frozen || (int) $artisan->score_prosartisan <= 0) {
            return 0;
        }

        $inactivityDays = $this->getInactivityDays($artisan);

        if ($inactivityDays < self::INACTIVITY_THRESHOLD_DAYS) {
            return 0;
        }

        $penalizedThisWeek = ScoreLedgerEntry::where('user_id', $artisan->id)
            ->where('event_type', 'inactivity_decay')
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        if ($penalizedThisWeek) {
            return 0;
        }

        ScoreLedgerEntry::create([
            'user_id' => $artisan->id,
            'event_type' => 'inactivity_decay',
            'points' => self::EVENT_POINTS['inactivity_decay'],
            'credibility_factor' => 1.00,
            'description' => "Inactivité depuis {$inactivityDays} jours",
        ]);

        $this->recalculateFromLedger($artisan);

        return abs(self::EVENT_POINTS['inactivity_decay']);
    }

    /**
     * Nombre de jours depuis la dernière activité de l'artisan : dernière
     * étape validée ou payée, ou dernier mouvement d'une de ses missions.
     * À défaut, depuis son inscription.
     */
    public function getInactivityDays(User $artisan): int
    {
        $lastJalon = DB::table('jalons')
            ->join('missions', 'jalons.mission_id', '=', 'missions.id')
            ->where('missions.artisan_id', $artisan->id)
            ->whereIn('jalons.statut', ['valide', 'paye'])
            ->max('jalons.updated_at');

        $lastMission = DB::table('missions')->where('artisan_id', $artisan->id)->max('updated_at');

        $lastActivity = collect([$lastJalon, $lastMission])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->max();

        return (int) ($lastActivity ?? $artisan->created_at)->diffInDays(now());
    }

    // ──────────────────────────────────────────────
    //  Recalcul pur à partir du Ledger
    /**
     * Somme les piliers d'évaluation pondérés par la maturité (10 clients distincts) et le Ledger pour score_prosartisan (0 à 1000).
     */
    public function recalculateFromLedger(User $artisan): int
    {
        if ($artisan->score_frozen) {
            return $artisan->score_prosartisan;
        }

        $row = DB::selectOne('
            SELECT
                AVG(fiabilite)  AS avg_fiabilite,
                AVG(integrite)  AS avg_integrite,
                AVG(qualite)    AS avg_qualite,
                AVG(reactivite) AS avg_reactivite,
                COUNT(*)        AS total_evaluations,
                COUNT(DISTINCT evaluateur_id) AS clients_distincts
            FROM evaluations
            WHERE evalue_id = ?
        ', [$artisan->id]);

        $evalScoreBase = 0;
        $excellent = false;
        $totalEvals = (int) ($row?->total_evaluations ?? 0);

        if ($row && $totalEvals > 0) {
            $f = (float) ($row->avg_fiabilite ?? 0);
            $i = (float) ($row->avg_integrite ?? 0);
            $q = (float) ($row->avg_qualite ?? 0);
            $r = (float) ($row->avg_reactivite ?? 0);

            // Somme brute des 4 piliers : Fiabilité (400) + Intégrité (300) + Qualité (200) + Réactivité (100).
            // Une étoile vaut 0 point, cinq étoiles le maximum du pilier.
            $rawCriteriaScore = self::pillarPoints($f, 'fiabilite') + self::pillarPoints($i, 'integrite')
                + self::pillarPoints($q, 'qualite') + self::pillarPoints($r, 'reactivite');

            // Condition d'excellence : au moins 3 critères avec moyenne >= 4.8 / 5 pour dépasser le seuil
            $countExcellence = 0;
            if ($f >= 4.8) {
                $countExcellence++;
            }
            if ($i >= 4.8) {
                $countExcellence++;
            }
            if ($q >= 4.8) {
                $countExcellence++;
            }
            if ($r >= 4.8) {
                $countExcellence++;
            }

            $excellent = $countExcellence >= 3;

            // Facteur de maturité : il compte les clients distincts, pas les
            // évaluations. Dix notes d'un même client ne valent qu'un dixième
            // du score potentiel (anti-collusion).
            $volumeFactor = self::maturityFactor((int) ($row->clients_distincts ?? 0));
            $evalScoreBase = (int) round($rawCriteriaScore * $volumeFactor);
        }

        $ledgerEntries = ScoreLedgerEntry::where('user_id', $artisan->id)->get();

        if ($totalEvals === 0 && $ledgerEntries->isEmpty()) {
            if ($artisan->score_prosartisan !== 0) {
                $artisan->update(['score_prosartisan' => 0]);
            }

            return 0;
        }

        $ledgerSum = (int) $ledgerEntries->sum(function ($entry) {
            return $entry->points * $entry->credibility_factor;
        });

        $newScore = min(self::MAX_SCORE, max(self::MIN_SCORE, $evalScoreBase + $ledgerSum));

        // Le plafond d'excellence porte sur le score total, bonus du ledger
        // compris : sans trois critères à 4,8 au moins, aucun bonus ne fait
        // franchir le seuil (Règle d'or 14).
        if (! $excellent) {
            $newScore = min(self::excellenceThreshold(), $newScore);
        }

        $artisan->update(['score_prosartisan' => $newScore]);

        return $newScore;
    }

    // ──────────────────────────────────────────────
    //  Conversion des notes en points
    // ──────────────────────────────────────────────

    /**
     * Points d'un pilier pour une moyenne de 1 à 5 étoiles : une étoile vaut
     * 0 point, cinq étoiles le maximum du pilier. Une moyenne nulle signifie
     * « aucune note » et vaut 0.
     */
    public static function pillarPoints(float $average, string $pillar): float
    {
        if ($average <= 1.0) {
            return 0.0;
        }

        return min(1.0, ($average - 1.0) / 4.0) * self::pillarWeight($pillar);
    }

    public static function pillarWeight(string $pillar): int
    {
        return (int) config("prosartisan.score_prosartisan.weights.{$pillar}", 0);
    }

    /** Nombre de clients distincts à partir duquel le score potentiel est entier. */
    public static function maturityTarget(): int
    {
        return max(1, (int) config('prosartisan.score_prosartisan.maturity_clients_target', 10));
    }

    /** Part du score potentiel débloquée par le nombre de clients distincts ayant noté. */
    public static function maturityFactor(int $distinctClients): float
    {
        return min(1.0, max(0, $distinctClients) / self::maturityTarget());
    }

    /** Score au-delà duquel trois critères à 4,8 au moins sont exigés. */
    public static function excellenceThreshold(): int
    {
        return (int) config('prosartisan.score_prosartisan.excellence_threshold', 800);
    }

    /**
     * Recalcule le score de tous les comptes notés ou porteurs d'événements,
     * après un changement de formule. Les scores gelés ne bougent pas.
     *
     * @return array{comptes: int, modifies: int}
     */
    public function recalculateAll(): array
    {
        $ids = DB::table('evaluations')->distinct()->pluck('evalue_id')
            ->merge(DB::table('score_ledger_entries')->distinct()->pluck('user_id'))
            ->unique();

        $changed = 0;

        foreach (User::withTrashed()->whereIn('id', $ids)->lazyById() as $user) {
            $before = (int) $user->score_prosartisan;
            if ($this->recalculateFromLedger($user) !== $before) {
                $changed++;
            }
        }

        return ['comptes' => $ids->count(), 'modifies' => $changed];
    }

    // ──────────────────────────────────────────────
    //  Indice de Crédibilité Ck
    // ──────────────────────────────────────────────

    /**
     * Résout l'indice de crédibilité d'un évaluateur.
     *   - Client au KYC actif ayant plus de 3 missions terminées : 1.0
     *   - Client institutionnel (rôle non encore créé en production) : 1.5
     *   - Tout autre évaluateur : 0.1
     */
    public function resolveCredibility(?User $evaluateur): float
    {
        if (! $evaluateur) {
            return 0.1;
        }

        // Rôle absent de l'ENUM `users.role` en production : branche inactive tant
        // que la décision produit sur les clients institutionnels n'est pas prise.
        if ($evaluateur->role === 'client_b2b') {
            return 1.5;
        }

        if ($evaluateur->isKycActif()) {
            // La base stocke la clé d'état (« completed »), pas le nom de la classe.
            $completedMissionsCount = Mission::where('client_id', $evaluateur->id)
                ->where('status', 'completed')
                ->count();
            if ($completedMissionsCount > 3) {
                return 1.0;
            }
        }

        return 0.1;
    }

    /**
     * Moyenne et répartition des notes reçues (1 à 5 étoiles).
     *
     * Sans aucune évaluation, `rating` vaut `null` (« Non évalué ») et non 0
     * ni une note par défaut (Règle d'or 29). `distribution` donne, pour
     * chaque nombre d'étoiles, la part des évaluations entre 0 et 1.
     *
     * @return array{rating: float|null, ratings_count: int, distribution: array<int, float>}
     */
    public function ratingsSummary(User $user): array
    {
        $counts = Evaluation::where('evalue_id', $user->id)
            ->selectRaw('note, COUNT(*) as total')
            ->groupBy('note')
            ->pluck('total', 'note');

        $ratingsCount = (int) $counts->sum();
        $sum = $counts->reduce(fn (int $carry, $total, $note) => $carry + (int) $note * (int) $total, 0);

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $distribution[$star] = $ratingsCount > 0
                ? round((int) ($counts[$star] ?? 0) / $ratingsCount, 2)
                : 0.0;
        }

        return [
            'rating' => $ratingsCount > 0 ? round($sum / $ratingsCount, 1) : null,
            'ratings_count' => $ratingsCount,
            'distribution' => $distribution,
        ];
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

        $row = DB::selectOne('
            SELECT
                AVG(fiabilite)  AS avg_fiabilite,
                AVG(integrite)  AS avg_integrite,
                AVG(qualite)    AS avg_qualite,
                AVG(reactivite) AS avg_reactivite,
                AVG(note)       AS avg_note,
                COUNT(*)        AS total_evaluations,
                COUNT(DISTINCT evaluateur_id) AS clients_distincts
            FROM evaluations
            WHERE evalue_id = ?
        ', [$artisan->id]);

        $threshold = config('prosartisan.score_prosartisan.credit_threshold', 700);
        $goldenThreshold = (int) config('prosartisan.score_prosartisan.golden_marker_threshold', 700);
        $totalEvals = (int) ($row?->total_evaluations ?? 0);
        $distinctClients = (int) ($row?->clients_distincts ?? 0);
        $maturityTarget = self::maturityTarget();

        return [
            'score_prosartisan' => $calculatedScore,
            'micro_credit_eligible' => $calculatedScore >= $threshold,
            'is_golden_marker' => $calculatedScore >= $goldenThreshold,
            'golden_marker_threshold' => $goldenThreshold,
            'total_evaluations' => $totalEvals,
            // La maturité compte les clients distincts ayant noté le compte.
            'distinct_clients' => $distinctClients,
            'maturity_missions_target' => $maturityTarget,
            'maturity_missions_count' => min($maturityTarget, $distinctClients),
            'maturity_percentage' => round(self::maturityFactor($distinctClients) * 100, 1),
            'breakdown' => [
                'fiabilite' => round((float) ($row?->avg_fiabilite ?? 0), 1),
                'integrite' => round((float) ($row?->avg_integrite ?? 0), 1),
                'qualite' => round((float) ($row?->avg_qualite ?? 0), 1),
                'reactivite' => round((float) ($row?->avg_reactivite ?? 0), 1),
            ],
            'breakdown_points' => [
                'fiabilite' => (int) round(self::pillarPoints((float) ($row?->avg_fiabilite ?? 0), 'fiabilite')),
                'integrite' => (int) round(self::pillarPoints((float) ($row?->avg_integrite ?? 0), 'integrite')),
                'qualite' => (int) round(self::pillarPoints((float) ($row?->avg_qualite ?? 0), 'qualite')),
                'reactivite' => (int) round(self::pillarPoints((float) ($row?->avg_reactivite ?? 0), 'reactivite')),
            ],
            'max_points' => [
                'fiabilite' => self::pillarWeight('fiabilite'),
                'integrite' => self::pillarWeight('integrite'),
                'qualite' => self::pillarWeight('qualite'),
                'reactivite' => self::pillarWeight('reactivite'),
            ],
            'excellence_threshold' => self::excellenceThreshold(),
            'average_rating' => round((float) ($row?->avg_note ?? 0), 1),
        ];
    }

    /**
     * Détermine si l'artisan bénéficie du statut Marqueur Doré (artisan prioritaire).
     */
    public function isGoldenMarker(User $artisan): bool
    {
        return (int) $artisan->score_prosartisan >= (int) config('prosartisan.score_prosartisan.golden_marker_threshold', 700);
    }
}
