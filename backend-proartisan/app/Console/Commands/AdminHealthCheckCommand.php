<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminObservabilityService;
use App\Services\Admin\TelegramAlertService;
use Illuminate\Console\Command;

/**
 * Chantier C7 (P2-12) — vérification de santé du backoffice + alerte Telegram.
 *
 * Programmé toutes les 15 minutes (routes/console.php). N'envoie une alerte que
 * si au moins un signal critique est non nul, sauf `--force`.
 */
class AdminHealthCheckCommand extends Command
{
    protected $signature = 'admin:health-check {--force : Envoie le digest même sans signal critique}';

    protected $description = 'Contrôle les signaux critiques du backoffice et alerte sur Telegram le cas échéant.';

    public function handle(AdminObservabilityService $observability, TelegramAlertService $telegram): int
    {
        $counts = $observability->criticalCounts();
        $total = array_sum($counts);

        $this->table(array_keys($counts), [array_values($counts)]);

        if ($total === 0 && ! $this->option('force')) {
            $this->info('Aucun signal critique. Pas d\'alerte envoyée.');

            return self::SUCCESS;
        }

        $lines = [
            '<b>ProsArtisan — Santé backoffice</b>',
            '',
            sprintf('• Jobs en échec : <b>%d</b>', $counts['failed_jobs']),
            sprintf('• Paiements KO (24 h) : <b>%d</b>', $counts['failed_payments_24h']),
            sprintf('• Fraude GPS J-Code (7 j) : <b>%d</b>', $counts['gps_fraud_7d']),
            sprintf('• Missions bloquées seuil Référent : <b>%d</b>', $counts['referent_blocked']),
            '',
            now()->format('d/m/Y H:i'),
        ];

        $sent = $telegram->send(implode("\n", $lines));

        if ($sent) {
            $this->info('Alerte Telegram envoyée.');
        } elseif (! $telegram->isConfigured()) {
            $this->warn('Telegram non configuré — alerte non envoyée.');
        } else {
            $this->error('Échec de l\'envoi Telegram.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
