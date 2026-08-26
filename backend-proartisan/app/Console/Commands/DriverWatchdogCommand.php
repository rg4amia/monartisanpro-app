<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CRON Watchdog Livreur — Réaffectation automatique des courses inactives.
 *
 * JUSTIFICATION PRD (§5 — Logistique & Livreurs) :
 * Si un livreur accepte une course mais reste immobile pendant plus de 15 minutes
 * (configurable via setting `driver_watchdog_timeout_minutes`), la course lui est
 * automatiquement retirée et remise dans le radar pour un autre livreur.
 *
 * Protection anti-boucle : max 3 réaffectations par commande (configurable via
 * `driver_max_reassignments`). Au-delà, alerte admin sans remise en radar.
 *
 * Schedule : toutes les 5 minutes (via routes/console.php).
 */
class DriverWatchdogCommand extends Command
{
    protected $signature = 'prosartisan:driver-watchdog
                            {--dry-run : Affiche les commandes concernées sans les réaffecter}';

    protected $description = 'Retire automatiquement les courses aux livreurs inactifs et relance le radar.';

    public function __construct(private OrderService $orderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $timeoutMinutes   = (int) Setting::getValueByKey('driver_watchdog_timeout_minutes', 15);
        $maxReassignments = (int) Setting::getValueByKey('driver_max_reassignments', 3);
        $cutoff           = now()->subMinutes($timeoutMinutes);
        $isDryRun         = $this->option('dry-run');

        $this->info("=== Driver Watchdog (délai : {$timeoutMinutes} min, max réaffectations : {$maxReassignments}) ===");

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        // Commandes avec livreur assigné depuis plus de {timeout} minutes
        $staleOrders = Order::query()
            ->where('status', 'driver_assigned')
            ->whereNotNull('driver_assigned_at')
            ->where('driver_assigned_at', '<', $cutoff)
            ->with(['driver', 'client', 'supplier'])
            ->get();

        if ($staleOrders->isEmpty()) {
            $this->info('Aucune course inactive détectée.');
            Log::info('[DriverWatchdog] Aucune course éligible.');

            return self::SUCCESS;
        }

        $this->info("Courses inactives détectées : {$staleOrders->count()}");

        $reassigned = 0;
        $escalated  = 0;
        $skipped    = 0;

        foreach ($staleOrders as $order) {
            $driverName = $order->driver?->name ?? "#{$order->driver_id}";
            $minutesSinceAssignment = now()->diffInMinutes($order->driver_assigned_at);
            $label = "Commande #{$order->id} (Livreur: {$driverName}, assigné il y a {$minutesSinceAssignment} min, tentative {$order->driver_reassignment_count}/{$maxReassignments})";

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Serait réaffectée : {$label}");
                continue;
            }

            // Protection anti-boucle infinie
            if ($order->driver_reassignment_count >= $maxReassignments) {
                $this->warn("  ⚠️ Max atteint, escalade admin : {$label}");

                try {
                    app(\App\Services\NotificationService::class)->sendAdmin(
                        'fraud_alert',
                        'Commande sans livreur — escalade requise',
                        "La commande #{$order->id} a atteint {$maxReassignments} réaffectations sans succès. Intervention manuelle requise.",
                        ['order_id' => $order->id, 'reassignment_count' => $order->driver_reassignment_count]
                    );
                } catch (\Throwable $e) {
                    Log::warning("[DriverWatchdog] Notification escalade admin échouée: " . $e->getMessage());
                }

                $escalated++;
                continue;
            }

            try {
                $this->orderService->reassignDriver(
                    $order,
                    "inactivité > {$timeoutMinutes} min après acceptation"
                );
                $this->info("  ✅ Réaffectée : {$label}");
                $reassigned++;
            } catch (\Throwable $e) {
                $this->error("  ❌ Erreur sur {$label} : {$e->getMessage()}");
                Log::error('[DriverWatchdog] Erreur de réaffectation', [
                    'order_id'  => $order->id,
                    'driver_id' => $order->driver_id,
                    'error'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        if (!$isDryRun) {
            $this->info("=== Résumé : {$reassigned} réaffectées, {$escalated} escaladées admin, {$skipped} erreurs ===");
            Log::info('[DriverWatchdog] Traitement terminé', [
                'reassigned' => $reassigned,
                'escalated'  => $escalated,
                'skipped'    => $skipped,
                'timeout_min'=> $timeoutMinutes,
            ]);
        }

        return self::SUCCESS;
    }
}
