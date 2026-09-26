<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\WalletOperation;
use App\Enums\WalletType;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\States\Mission\MissionState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Audit en lecture seule de la trésorerie : séquestre des missions, séquestre
 * des commandes, et cohérence des portefeuilles avec le ledger (Règle d'Or 9).
 *
 * Les statuts sont filtrés sur les valeurs FSM réellement stockées (Règle d'Or
 * 27) et affichés en français. Aucune correction n'est automatique : un solde
 * financier se régularise après examen, jamais par un réalignement aveugle.
 */
class ReconcileTreasuryCommand extends Command
{
    protected $signature = 'prosartisan:reconcile-treasury
                            {--fix : Sans effet — conservé pour compatibilité ; toute régularisation est manuelle}';

    protected $description = 'Vérifie l\'intégrité comptable du séquestre (missions, commandes) et la cohérence des portefeuilles avec le ledger.';

    /** Missions dont le séquestre a été constitué (valeurs FSM stockées). */
    private const FUNDED_MISSION_STATUSES = ['funded_locked', 'in_progress', 'pending_approval', 'completed', 'disputed'];

    public function handle(): int
    {
        $this->info('=== ProsArtisan : Réconciliation de la Trésorerie et Séquestre ===');

        if ($this->option('fix')) {
            $this->warn('--fix est sans effet : aucune correction automatique des soldes. Chaque écart se régularise après examen.');
        }

        $anomalies = $this->auditMissions() + $this->auditOrders() + $this->auditWallets();

        $this->info("\n=== Rapport d'audit de trésorerie ===");

        if ($anomalies === 0) {
            $this->info('✅ Trésorerie intègre : aucun écart détecté.');
            Log::info('[TreasuryReconcile] Audit réussi : 0 écart.');

            return self::SUCCESS;
        }

        $this->warn("⚠️ Audit terminé : {$anomalies} anomalie(s) détectée(s).");
        Log::warning("[TreasuryReconcile] Anomalies détectées : {$anomalies}");

        return self::FAILURE;
    }

    /**
     * Dépôts confirmés (+ remises promo) comparés au montant de la mission :
     * égalité exigée en paiement intégral ; en mode hybride, les jalons se
     * paient au fil du chantier — le dépôt ne doit jamais dépasser le total,
     * et doit l'atteindre une fois la mission terminée.
     */
    private function auditMissions(): int
    {
        $this->info("\n1. Vérification du séquestre des missions...");
        $anomalies = 0;

        Mission::query()
            ->whereIn('status', self::FUNDED_MISSION_STATUSES)
            ->orderBy('id')
            ->each(function (Mission $mission) use (&$anomalies) {
                $deposits = Transaction::query()
                    ->where('type', 'acompte')
                    ->where('statut', PaymentStatus::CONFIRME)
                    ->where('wallet_dest', 'escrow_mission_'.$mission->id)
                    ->get(['montant', 'metadata']);

                $covered = (int) $deposits->sum('montant')
                    + (int) $deposits->sum(fn (Transaction $t) => (int) ($t->metadata['discount_amount'] ?? 0));
                $expected = (int) $mission->montant_total;
                $status = (string) $mission->status;

                $isHybrid = $mission->payment_type === 'hybrid';
                $gap = $isHybrid && $status !== 'completed'
                    ? $covered > $expected
                    : $covered !== $expected;

                if ($gap) {
                    $mode = $isHybrid ? 'hybride' : 'paiement intégral';
                    $this->warn("  ⚠️ Écart mission #{$mission->id} (".MissionState::labelFor($status).", {$mode}) : attendu {$expected} FCFA, couvert {$covered} FCFA (dépôts + remises), différence ".($covered - $expected).' FCFA');
                    $anomalies++;
                }
            });

        return $anomalies;
    }

    /**
     * Dépôts confirmés comparés au total de la commande (ou du groupe
     * multi-fournisseurs).
     *
     * Depuis la course « à la Yango » (Chantier 10), une commande livrée est
     * encaissée en deux fois : le panier au paiement de la commande
     * (`acompte`, vers le séquestre de la commande ou du groupe), puis la
     * course à la livraison (`paiement_livraison`, toujours vers le séquestre
     * de la commande). `revealDeliveryFare` ajoute cette course au
     * `total_amount` dès la livraison : tant que le client ne l'a pas réglée,
     * elle n'est pas attendue dans le séquestre. Ne compter que les acomptes
     * signalait à tort chaque commande livrée.
     */
    private function auditOrders(): int
    {
        $this->info('
2. Vérification du séquestre des commandes...');
        $anomalies = 0;
        $checkedGroups = [];

        Order::query()->orderBy('id')->each(function (Order $order) use (&$anomalies, &$checkedGroups) {
            if ($order->order_group_id) {
                if (isset($checkedGroups[$order->order_group_id])) {
                    return;
                }
                $checkedGroups[$order->order_group_id] = true;

                $orders = Order::where('order_group_id', $order->order_group_id)->get();
                $expected = (int) $orders->sum(fn (Order $o) => $this->expectedOrderDeposit($o));
                $deposited = $this->confirmedDeposits('escrow_group_'.$order->order_group_id)
                    + $this->confirmedDeposits($orders->map(fn (Order $o) => 'escrow_order_'.$o->id)->all(), ['paiement_livraison']);
                $label = "groupe {$order->order_group_id}";
            } else {
                $expected = $this->expectedOrderDeposit($order);
                $deposited = $this->confirmedDeposits('escrow_order_'.$order->id, ['acompte', 'paiement_livraison']);
                $label = "commande #{$order->id} (".Order::statusLabel((string) $order->status).')';
            }

            if ($deposited > 0 && $deposited !== $expected) {
                $this->warn("  ⚠️ Écart {$label} : attendu {$expected} FCFA, déposé {$deposited} FCFA, différence ".($deposited - $expected).' FCFA');
                $anomalies++;
            }
        });

        return $anomalies;
    }

    /** Montant encaissé attendu : le total, moins la course révélée et pas encore réglée. */
    private function expectedOrderDeposit(Order $order): int
    {
        return (int) $order->total_amount - $order->deliveryFareDue();
    }

    /**
     * Règle d'Or 9 : le solde fait foi par la somme du ledger. Toute colonne
     * `wallet_*` qui en diverge a été affectée hors ledger ; un solde de ledger
     * négatif signale un débit sans crédit correspondant.
     */
    private function auditWallets(): int
    {
        $this->info("\n3. Vérification de la cohérence des portefeuilles avec le ledger...");
        $anomalies = 0;

        $userIds = User::query()
            ->where(fn ($q) => $q->where('wallet_materiaux', '!=', 0)->orWhere('wallet_mo', '!=', 0))
            ->pluck('id')
            ->merge(WalletTransaction::query()->distinct()->pluck('user_id'))
            ->unique()
            ->sort();

        $unhandled = WalletTransaction::query()
            ->whereIn('operation', [WalletOperation::REVERSE_ENTRY->value, WalletOperation::FEE_CUT->value])
            ->count();
        if ($unhandled > 0) {
            $this->warn("  ⚠️ {$unhandled} écriture(s) de type contre-passation / prélèvement de frais : non prises en compte par cet audit, à vérifier manuellement.");
            $anomalies++;
        }

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            foreach (WalletType::cases() as $walletType) {
                // Valeur brute de la colonne : l'accesseur User::getWalletMoAttribute()
                // renvoie déjà la somme du ledger et masquerait tout écart. La
                // colonne reste lue telle quelle par les requêtes SQL (tableaux de
                // bord, filtres `wallet_mo > 0`) : elle doit suivre le ledger.
                $stored = (int) ($user->getRawOriginal($walletType->columnName()) ?? 0);
                $ledger = $this->ledgerBalance($user->id, $walletType);
                $label = $walletType === WalletType::WALLET_MO ? "main d'œuvre" : 'matériaux';

                if ($ledger < 0) {
                    $this->error("  ❌ Utilisateur #{$user->id} : solde {$label} négatif dans le ledger ({$ledger} FCFA).");
                    $anomalies++;
                } elseif ($stored !== $ledger) {
                    $this->warn("  ⚠️ Utilisateur #{$user->id} : solde {$label} stocké {$stored} FCFA ≠ ledger {$ledger} FCFA (différence ".($stored - $ledger).' FCFA).');
                    $anomalies++;
                }
            }
        }

        return $anomalies;
    }

    /**
     * @param  string|list<string>  $walletDest
     * @param  list<string>  $types
     */
    private function confirmedDeposits(string|array $walletDest, array $types = ['acompte']): int
    {
        return (int) Transaction::query()
            ->whereIn('type', $types)
            ->where('statut', PaymentStatus::CONFIRME)
            ->whereIn('wallet_dest', (array) $walletDest)
            ->sum('montant');
    }

    private function ledgerBalance(int $userId, WalletType $walletType): int
    {
        $base = WalletTransaction::query()
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType->value);

        $credits = (clone $base)
            ->whereIn('operation', [WalletOperation::CREDIT->value, WalletOperation::DEBLOCAGE->value])
            ->sum('montant');

        $debits = (clone $base)
            ->whereIn('operation', [WalletOperation::DEBIT->value, WalletOperation::BLOCAGE->value])
            ->sum('montant');

        return (int) $credits - (int) $debits;
    }
}
