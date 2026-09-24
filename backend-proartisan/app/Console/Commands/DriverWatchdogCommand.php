<?php

namespace App\Console\Commands;

use App\Models\DeliveryTracking;
use App\Models\Order;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CRON Watchdog Livreur — Surveillance et réaffectation automatique des courses.
 *
 * JUSTIFICATION PRD (§5 — Logistique & Livreurs) :
 * 1. Assignation initiale sans retrait : Si un livreur accepte une course mais reste immobile
 *    pendant plus de 15 minutes (configurable via `driver_watchdog_timeout_minutes`), la course
 *    lui est automatiquement retirée et remise dans le radar. Protection anti-boucle : max 3 réaffectations.
 *
 * 2. En transit (driver_picked_up) sans mouvement : Si le livreur a récupéré les matériaux
 *    mais n'envoie aucun point GPS depuis plus de 25 minutes (configurable via `driver_in_transit_timeout_minutes`),
 *    le système relance le livreur par SMS/notification et alerte l'admin.
 *    RÈGLE ABSOLUE : Les matériaux étant physiquement chez le transporteur, le séquestre et le
 *    statut de la commande sont strictement préservés (jamais d'annulation brutale).
 *
 * Schedule : toutes les 5 minutes (via routes/console.php).
 */
class DriverWatchdogCommand extends Command
{
    protected $signature = 'prosartisan:driver-watchdog
                            {--dry-run : Affiche les commandes concernées sans effectuer de modifications}';

    protected $description = 'Surveille les livreurs assignés (réaffectation) et en transit (relance GPS et alerte admin).';

    public function __construct(private OrderService $orderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Mode simulation activé : aucune modification ne sera enregistrée.');
        }

        $this->handleAssignedStaleOrders($isDryRun);
        $this->handleInTransitStaleOrders($isDryRun);

        return self::SUCCESS;
    }

    /**
     * Volet 1 : Commandes au statut driver_assigned inactives (avant retrait chez le fournisseur).
     */
    private function handleAssignedStaleOrders(bool $isDryRun): void
    {
        $timeoutMinutes = (int) Setting::getValueByKey('driver_watchdog_timeout_minutes', 15);
        $maxReassignments = (int) Setting::getValueByKey('driver_max_reassignments', 3);
        $cutoff = now()->subMinutes($timeoutMinutes);

        $this->info("--- Volet 1: Livreur assigné sans retrait (délai : {$timeoutMinutes} min, max réaffectations : {$maxReassignments}) ---");

        $staleOrders = Order::query()
            ->where('status', 'driver_assigned')
            ->whereNotNull('driver_assigned_at')
            ->where('driver_assigned_at', '<', $cutoff)
            ->with(['driver', 'client', 'supplier'])
            ->get();

        if ($staleOrders->isEmpty()) {
            $this->info('Aucune course inactive détectée pour le statut driver_assigned.');

            return;
        }

        $this->info("Courses assignées inactives détectées : {$staleOrders->count()}");

        $reassigned = 0;
        $escalated = 0;
        $skipped = 0;

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
                $this->warn("  ⚠️ Max réaffectations atteint, escalade admin : {$label}");

                try {
                    app(NotificationService::class)->sendAdmin(
                        'fraud_alert',
                        'Commande sans livreur — escalade requise',
                        "La commande #{$order->id} a atteint {$maxReassignments} réaffectations sans succès. Intervention manuelle requise.",
                        ['order_id' => $order->id, 'reassignment_count' => $order->driver_reassignment_count]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[DriverWatchdog] Notification escalade admin échouée: '.$e->getMessage());
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
                    'order_id' => $order->id,
                    'driver_id' => $order->driver_id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        if (! $isDryRun) {
            $this->info("Résumé Volet 1 : {$reassigned} réaffectées, {$escalated} escaladées admin, {$skipped} erreurs");
        }
    }

    /**
     * Volet 2 : Commandes au statut driver_picked_up inactives en cours de route.
     * Les matériaux sont déjà chez le livreur : aucun retrait de course ni annulation,
     * relance SMS/notification et alerte admin pour intervention avec séquestre préservé.
     */
    private function handleInTransitStaleOrders(bool $isDryRun): void
    {
        $timeoutMinutes = (int) Setting::getValueByKey(
            'driver_in_transit_timeout_minutes',
            config('prosartisan.delivery.in_transit_timeout_minutes', 25)
        );
        $cooldownMinutes = (int) config('prosartisan.delivery.in_transit_alert_cooldown_minutes', 30);
        $cutoff = now()->subMinutes($timeoutMinutes);
        $cooldownCutoff = now()->subMinutes($cooldownMinutes);

        $this->info("--- Volet 2: Livreur en transit (driver_picked_up, inactivité > {$timeoutMinutes} min) ---");

        $inTransitOrders = Order::query()
            ->where('status', 'driver_picked_up')
            ->with(['driver', 'client', 'supplier'])
            ->get();

        if ($inTransitOrders->isEmpty()) {
            $this->info('Aucune course en transit détectée.');

            return;
        }

        $alerted = 0;
        $activeCount = 0;

        foreach ($inTransitOrders as $order) {
            // Déterminer la date de dernière activité (dernier point GPS ou date de retrait)
            $lastTrackingAt = DeliveryTracking::query()
                ->where('order_id', $order->id)
                ->latest('created_at')
                ->value('created_at');

            $lastActivityAt = $lastTrackingAt
                ? Carbon::parse($lastTrackingAt)
                : ($order->driver_picked_up_at ?? $order->updated_at);

            // Si le livreur a bougé récemment, il est en route normalement
            if ($lastActivityAt && $lastActivityAt->isAfter($cutoff)) {
                $activeCount++;

                continue;
            }

            // Anti-spam : vérifier le délai de relance
            if ($order->driver_stalled_alert_at && $order->driver_stalled_alert_at->isAfter($cooldownCutoff)) {
                $this->line("  ℹ️ Commande #{$order->id} : alerte déjà émise il y a moins de {$cooldownMinutes} min. Ignorée.");

                continue;
            }

            $driverName = $order->driver?->name ?? "#{$order->driver_id}";
            $minutesInactive = $lastActivityAt ? now()->diffInMinutes($lastActivityAt) : $timeoutMinutes;
            $label = "Commande #{$order->id} (Livreur: {$driverName}, inactif depuis {$minutesInactive} min)";

            if ($isDryRun) {
                $this->warn("  [DRY-RUN] Livreur en transit immobile : {$label} (relance SMS + alerte admin)");

                continue;
            }

            // 1. Relance Livreur par notification / SMS
            try {
                if ($order->driver) {
                    app(NotificationService::class)->send(
                        $order->driver,
                        'delivery_alert',
                        'Confirmation de livraison requise',
                        "Commande #{$order->id} : votre position GPS n'a pas été actualisée depuis plus de {$timeoutMinutes} minutes. Veuillez confirmer que vous êtes en route vers le chantier."
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[DriverWatchdog] Relance livreur échouée: '.$e->getMessage(), ['order_id' => $order->id]);
            }

            // 2. Alerte Admin pour supervision (sans détruire le séquestre)
            try {
                app(NotificationService::class)->sendAdmin(
                    'delivery_stalled',
                    'Livreur immobile en cours de livraison',
                    "Le livreur #{$order->driver_id} ({$driverName}) avec les matériaux de la commande #{$order->id} n'a pas actualisé sa position depuis {$minutesInactive} minutes. Séquestre préservé.",
                    [
                        'order_id' => $order->id,
                        'driver_id' => $order->driver_id,
                        'minutes_inactive' => $minutesInactive,
                        'last_activity_at' => $lastActivityAt?->toIso8601String(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[DriverWatchdog] Alerte admin échouée: '.$e->getMessage(), ['order_id' => $order->id]);
            }

            // 3. Mettre à jour l'horodatage d'alerte sur la commande
            $order->update(['driver_stalled_alert_at' => now()]);
            $this->warn("  ⚠️ Relance et alerte admin émises : {$label}");
            $alerted++;
        }

        if (! $isDryRun) {
            $this->info("Résumé Volet 2 : {$alerted} alertées, {$activeCount} actives en mouvement");
            Log::info('[DriverWatchdog] Volet transit terminé', [
                'alerted' => $alerted,
                'active' => $activeCount,
                'timeout_minutes' => $timeoutMinutes,
            ]);
        }
    }
}
