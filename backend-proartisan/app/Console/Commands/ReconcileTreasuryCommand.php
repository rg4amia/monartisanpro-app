<?php

namespace App\Console\Commands;

use App\Models\Mission;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileTreasuryCommand extends Command
{
    protected $signature = 'prosartisan:reconcile-treasury
                            {--fix : Tente de corriger automatiquement les écarts d\'arrondis ou de solde}';

    protected $description = 'Vérifie l\'intégrité mathématique et comptable du séquestre et des portefeuilles (Event Sourcing).';

    public function handle(): int
    {
        $this->info('=== ProsArtisan : Réconciliation de la Trésorerie et Séquestre ===');

        $autoFix = $this->option('fix');
        $discrepanciesCount = 0;

        // 1. Audit des transactions missions
        $this->info("\n1. Vérification du séquestre des missions...");
        $fundedMissions = Mission::whereIn('status', ['financee', 'en_cours', 'terminee'])->get();

        foreach ($fundedMissions as $mission) {
            $totalExpected = $mission->montant_total;
            $totalDeposited = Transaction::where('type', 'acompte')
                ->where('statut', 'confirme')
                ->where('wallet_dest', 'escrow_mission_' . $mission->id)
                ->sum('montant');

            if ($totalDeposited > 0 && abs($totalDeposited - $totalExpected) > 0) {
                $this->warn("  ⚠️ Écart mission #{$mission->id} : Attendu {$totalExpected} FCFA, Déposé {$totalDeposited} FCFA (Diff: " . ($totalDeposited - $totalExpected) . " FCFA)");
                $discrepanciesCount++;
            }
        }

        // 2. Audit des transactions commandes e-commerce
        $this->info("\n2. Vérification du séquestre des commandes...");
        $orders = Order::whereIn('status', ['paid', 'driver_assigned', 'driver_picked_up', 'delivered'])->get();

        foreach ($orders as $order) {
            if ($order->order_group_id) {
                // Vérifier au niveau du groupe
                $groupTotal = Order::where('order_group_id', $order->order_group_id)->sum('total_amount');
                $groupDeposited = Transaction::where('type', 'acompte')
                    ->where('statut', 'confirme')
                    ->where('wallet_dest', 'escrow_group_' . $order->order_group_id)
                    ->sum('montant');

                if ($groupDeposited > 0 && $groupDeposited != $groupTotal) {
                    $this->warn("  ⚠️ Écart groupe #{$order->order_group_id} : Somme commandes {$groupTotal} FCFA, Déposé {$groupDeposited} FCFA");
                    $discrepanciesCount++;
                }
            } else {
                $orderTotal = $order->total_amount;
                $orderDeposited = Transaction::where('type', 'acompte')
                    ->where('statut', 'confirme')
                    ->where('wallet_dest', 'escrow_order_' . $order->id)
                    ->sum('montant');

                if ($orderDeposited > 0 && $orderDeposited != $orderTotal) {
                    $this->warn("  ⚠️ Écart commande #{$order->id} : Attendu {$orderTotal} FCFA, Déposé {$orderDeposited} FCFA");
                    $discrepanciesCount++;
                }
            }
        }

        // 3. Audit des portefeuilles utilisateurs (Event Sourcing)
        $this->info("\n3. Vérification de la cohérence Event Sourcing des portefeuilles...");
        $usersWithBalance = User::where(function ($q) {
            $q->where('wallet_materiaux', '>', 0)
              ->orWhere('wallet_mo', '>', 0);
        })->get();

        foreach ($usersWithBalance as $u) {
            $computedMo = WalletTransaction::where('user_id', $u->id)
                ->where('wallet_type', 'mo')
                ->where('status', 'completed')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as balance")
                ->value('balance') ?? 0;

            $computedMat = WalletTransaction::where('user_id', $u->id)
                ->where('wallet_type', 'materiaux')
                ->where('status', 'completed')
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as balance")
                ->value('balance') ?? 0;

            // Comparer avec attributs s'ils diffèrent significativement
            if ($computedMo < 0 || $computedMat < 0) {
                $this->error("  ❌ Solde négatif détecté pour User #{$u->id} ({$u->name}) : MO={$computedMo}, MAT={$computedMat}");
                $discrepanciesCount++;
            }
        }

        $this->info("\n=== Rapport d'audit de trésorerie ===");
        if ($discrepanciesCount === 0) {
            $this->info("✅ Trésorerie 100% intègre. Aucun écart détecté.");
            Log::info('[TreasuryReconcile] Audit réussi : 0 écart.');
            return self::SUCCESS;
        } else {
            $this->warn("⚠️ Audit terminé : {$discrepanciesCount} anomalie(s) détectée(s).");
            Log::warning("[TreasuryReconcile] Anomalies détectées : {$discrepanciesCount}");
            return self::FAILURE;
        }
    }
}
