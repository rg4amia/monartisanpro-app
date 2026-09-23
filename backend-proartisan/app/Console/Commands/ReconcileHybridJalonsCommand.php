<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\WalletOperation;
use App\Enums\WalletType;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Régularise le séquestre des missions hybrides après le correctif de
 * cloisonnement par mission (Règle d'Or 68).
 *
 * Avant ce correctif, un paiement de jalon confirmé par webhook n'était jamais
 * consigné au séquestre, et un jalon hybride non financé se libérait en
 * puisant dans le `wallet_mo` commun, donc dans le séquestre d'autres missions.
 * Cette commande :
 *   1. finance les jalons payés mais jamais financés (avec --fix) — y compris
 *      ceux déjà libérés, ce qui reconstitue le séquestre des autres missions ;
 *   2. signale les doubles paiements d'un même jalon (remboursement manuel) ;
 *   3. signale les missions hybrides dont le ledger MO reste en déficit
 *      (libérations sans paiement client correspondant — arbitrage manuel).
 */
class ReconcileHybridJalonsCommand extends Command
{
    protected $signature = 'prosartisan:reconcile-hybrid-jalons
                            {--fix : Finance au séquestre les jalons payés par le client mais jamais consignés}';

    protected $description = 'Détecte et régularise les jalons hybrides payés mais non financés, les doubles paiements et les déficits de séquestre par mission.';

    public function handle(WalletService $walletService): int
    {
        $fix = (bool) $this->option('fix');

        $this->info('=== ProsArtisan : Réconciliation du séquestre hybride ===');
        $this->line($fix ? 'Mode correction (--fix).' : 'Mode rapport (aucune écriture). Relancer avec --fix pour régulariser.');

        $toFund = 0;
        $funded = 0;
        $doublePayments = 0;
        $orphans = 0;
        /** @var array<int, int> $pendingByMission montant qui serait financé, par mission (mode rapport) */
        $pendingByMission = [];

        // 1 & 2. Paiements de jalon confirmés
        $this->info("\n1. Paiements de jalon confirmés...");

        $payments = Transaction::query()
            ->where('type', 'acompte')
            ->where('statut', PaymentStatus::CONFIRME)
            ->where('metadata->payment_type', 'jalon')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $jalon = Jalon::with('mission')->find($payment->metadata['jalon_id'] ?? null);

            if (! $jalon || ! $jalon->mission) {
                $this->warn("  ⚠️ Transaction #{$payment->id} : jalon introuvable (orphelin, à examiner).");
                $orphans++;

                continue;
            }

            $label = "Jalon #{$jalon->id} (mission #{$jalon->mission_id}, statut {$jalon->statut}, {$jalon->montant} FCFA)";
            $funding = $this->fundingEntry($jalon);

            if ($funding) {
                if ((int) $funding->transaction_id !== (int) $payment->id) {
                    $this->warn("  ⚠️ Double paiement : {$label} déjà financé par la transaction #{$funding->transaction_id} ; la transaction #{$payment->id} ({$payment->montant} FCFA) est à rembourser au client.");
                    $doublePayments++;
                }

                continue;
            }

            if (isset($pendingByMission[$jalon->mission_id.':'.$jalon->id])) {
                $this->warn("  ⚠️ Double paiement : {$label} également réglé par la transaction #{$payment->id} ({$payment->montant} FCFA), à rembourser au client.");
                $doublePayments++;

                continue;
            }

            $toFund++;

            if (! $fix) {
                $this->line("  • À financer : {$label}, payé par la transaction #{$payment->id}.");
                $pendingByMission[$jalon->mission_id.':'.$jalon->id] = $jalon->montant;

                continue;
            }

            if ($walletService->fundHybridJalon($jalon, $payment, regularisation: true)) {
                $this->info("  ✅ Financé : {$label}, depuis la transaction #{$payment->id}.");
                $funded++;
            }
        }

        // 3. Déficits de séquestre MO par mission hybride
        $this->info("\n2. Déficits de séquestre des missions hybrides...");
        $deficits = 0;

        Mission::query()
            ->where('payment_type', 'hybrid')
            ->whereNotNull('artisan_id')
            ->orderBy('id')
            ->each(function (Mission $mission) use (&$deficits, $pendingByMission) {
                $balance = $this->laborLedgerBalance($mission);

                foreach ($pendingByMission as $key => $montant) {
                    if (str_starts_with($key, $mission->id.':')) {
                        $balance += $montant;
                    }
                }

                if ($balance < 0) {
                    $this->error("  ❌ Mission #{$mission->id} : déficit de ".abs($balance)." FCFA — libération(s) de jalon sans paiement client correspondant, réglée(s) avec le séquestre d'autres missions de l'artisan #{$mission->artisan_id}. Arbitrage manuel requis.");
                    $deficits++;
                }
            });

        $this->info("\n=== Rapport ===");
        $this->line("Jalons payés non financés : {$toFund}".($fix ? " (dont {$funded} régularisé(s))" : ''));
        $this->line("Doubles paiements à rembourser : {$doublePayments}");
        $this->line("Missions en déficit (arbitrage manuel) : {$deficits}");
        $this->line("Paiements orphelins : {$orphans}");

        $remaining = ($fix ? $toFund - $funded : $toFund) + $doublePayments + $deficits + $orphans;

        Log::info('[ReconcileHybridJalons] Réconciliation terminée', [
            'fix' => $fix,
            'to_fund' => $toFund,
            'funded' => $funded,
            'double_payments' => $doublePayments,
            'deficits' => $deficits,
            'orphans' => $orphans,
        ]);

        if ($remaining === 0) {
            $this->info('✅ Séquestre hybride cohérent.');

            return self::SUCCESS;
        }

        $this->warn("⚠️ {$remaining} point(s) restant(s) à traiter.");

        return self::FAILURE;
    }

    private function fundingEntry(Jalon $jalon): ?WalletTransaction
    {
        return WalletTransaction::query()
            ->where('jalon_id', $jalon->id)
            ->where('wallet_type', WalletType::WALLET_MO->value)
            ->where('operation', WalletOperation::CREDIT->value)
            ->where('metadata->type', 'escrow_mo_jalon')
            ->first();
    }

    /** Solde MO brut (non borné à 0) d'une mission dans le ledger. */
    private function laborLedgerBalance(Mission $mission): int
    {
        $base = WalletTransaction::query()
            ->where('user_id', $mission->artisan_id)
            ->where('mission_id', $mission->id)
            ->where('wallet_type', WalletType::WALLET_MO->value);

        $credits = (clone $base)
            ->whereIn('operation', [WalletOperation::CREDIT->value, WalletOperation::DEBLOCAGE->value])
            ->sum('montant');

        $debits = (clone $base)
            ->whereIn('operation', [WalletOperation::DEBIT->value, WalletOperation::BLOCAGE->value])
            ->sum('montant');

        return (int) $credits - (int) $debits;
    }
}
