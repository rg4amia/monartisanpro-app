<?php

namespace App\Services\Admin;

use App\Models\ScoreLedgerEntry;
use App\Models\Setting;
use App\Services\ScoreService;

/**
 * Pilotage, depuis le backoffice, de la dégradation d'inactivité du Score
 * ProsArtisan : état, artisans visés, points déjà retirés, activation et
 * désactivation auditées (Règle d'or 97).
 */
class InactivityDecayAdminService
{
    /** Groupe des réglages de score, tenu hors de l'éditeur générique des réglages. */
    public const GROUP = 'score_rules';

    public function __construct(
        private ScoreService $scores,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * @return array{enabled: bool, source: string, threshold_days: int, points: int, concerned: int, penalties_30d: int, points_removed_30d: int, updated_at: string|null}
     */
    public function overview(): array
    {
        $setting = Setting::where('key', ScoreService::INACTIVITY_DECAY_SETTING)->first();
        $recent = ScoreLedgerEntry::where('event_type', 'inactivity_decay')
            ->where('created_at', '>=', now()->subDays(30));

        return [
            'enabled' => ScoreService::inactivityDecayEnabled(),
            // « reglage » : posé depuis le backoffice ; « configuration » : valeur du serveur.
            'source' => $setting ? 'reglage' : 'configuration',
            'threshold_days' => ScoreService::INACTIVITY_THRESHOLD_DAYS,
            'points' => ScoreService::inactivityDecayPoints(),
            'concerned' => $this->scores->countInactiveArtisans(),
            'penalties_30d' => (int) (clone $recent)->count(),
            'points_removed_30d' => (int) abs((int) (clone $recent)->sum('points')),
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    /** Active ou désactive la dégradation. Retourne faux si l'état demandé était déjà en vigueur. */
    public function setEnabled(bool $enabled): bool
    {
        $before = ScoreService::inactivityDecayEnabled();
        $concerned = $this->scores->countInactiveArtisans();

        Setting::updateOrCreate(
            ['key' => ScoreService::INACTIVITY_DECAY_SETTING],
            [
                'value' => $enabled ? '1' : '0',
                'type' => 'boolean',
                'group' => self::GROUP,
                'label' => "Dégradation d'inactivité du Score ProsArtisan",
                'description' => 'Retire des points aux artisans sans activité. Se pilote depuis l\'onglet « Évaluations & Scores ».',
            ],
        );

        if ($before === $enabled) {
            return false;
        }

        $this->audit->log(
            $enabled ? 'score.inactivity_decay.enabled' : 'score.inactivity_decay.disabled',
            null,
            ['before' => $before, 'after' => $enabled, 'artisans_concernes' => $concerned],
            "Dégradation d'inactivité du score",
        );

        return true;
    }
}
