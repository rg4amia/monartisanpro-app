<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\DriverCashout;
use App\Models\OrderDispute;
use App\Models\OrderDisputeDebt;
use App\Models\OrderDisputeDebtEntry;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Dettes nées d'un litige de commande (Chantier 22) : ProsArtisan a avancé le
 * remboursement du client, le responsable doit la somme.
 *
 * Le recouvrement passe par deux voies, toutes deux tracées dans
 * `order_dispute_debt_entries` et dans le ledger des portefeuilles :
 * prélèvement sur les gains du débiteur, ou règlement direct par Mobile Money.
 * Tant qu'une dette est en cours, le profil du débiteur est bloqué
 * (`EnsureNoDisputeDebt`).
 */
class OrderDisputeDebtService
{
    public function __construct(
        private WalletService $wallets,
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
    ) {}

    public function hasOpenDebt(User $user): bool
    {
        return OrderDisputeDebt::where('user_id', $user->id)->where('statut', OrderDisputeDebt::STATUT_EN_COURS)->exists();
    }

    /**
     * Identifiants des utilisateurs bloqués par une dette en cours.
     *
     * @return list<int>
     */
    public function debtorIds(): array
    {
        return OrderDisputeDebt::where('statut', OrderDisputeDebt::STATUT_EN_COURS)->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Fonds du portefeuille réellement disponibles : hors retraits demandés et
     * hors sommes gelées par un litige ouvert.
     */
    public function availableBalance(User $user, WalletType $walletType): int
    {
        $balance = (int) $user->fresh()->getWalletBalance($walletType);

        $reserved = $walletType === WalletType::WALLET_MATERIAUX
            ? (int) SupplierCashout::where('supplier_id', $user->id)
                ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
                ->sum('montant_brut')
            : (int) DriverCashout::where('driver_id', $user->id)
                ->whereIn('statut', DriverCashout::STATUTS_RESERVES)
                ->sum('montant_brut');

        return max(0, $balance - $reserved - $this->frozenFor($user));
    }

    /**
     * Sommes gelées chez un fournisseur par ses litiges de commande ouverts.
     */
    public function frozenFor(User $user): int
    {
        return (int) OrderDispute::where('frozen_user_id', $user->id)
            ->where('statut', OrderDispute::STATUT_OUVERT)
            ->sum('frozen_amount');
    }

    /**
     * Fait supporter une somme au responsable : prélève ce que son
     * portefeuille contient, et inscrit le reste en dette.
     *
     * @return array{collected: int, debt: ?OrderDisputeDebt}
     */
    public function charge(OrderDispute $dispute, User $responsible, WalletType $walletType, int $amount): array
    {
        if ($amount <= 0) {
            return ['collected' => 0, 'debt' => null];
        }

        $collected = min($amount, $this->availableBalance($responsible, $walletType));

        if ($collected > 0) {
            $this->moveToPlatform($responsible, $walletType, $collected, "Remboursement du litige de la commande #{$dispute->order_id}", [
                'order_id' => $dispute->order_id,
                'order_dispute_id' => $dispute->id,
                'type' => 'litige_commande_prelevement',
            ]);
        }

        $remaining = $amount - $collected;
        if ($remaining <= 0) {
            return ['collected' => $collected, 'debt' => null];
        }

        $debt = OrderDisputeDebt::create([
            'order_dispute_id' => $dispute->id,
            'user_id' => $responsible->id,
            'wallet_type' => $walletType->value,
            'montant' => $remaining,
            'statut' => OrderDisputeDebt::STATUT_EN_COURS,
        ]);

        $this->notifications->notify($responsible, 'litige_commande.dette_creee.responsable', [
            'commande' => $dispute->order_id,
            'montant' => $this->fcfa($remaining),
        ], ['order_id' => $dispute->order_id, 'debt_id' => $debt->id, 'action' => 'pay_dispute_debt']);

        return ['collected' => $collected, 'debt' => $debt];
    }

    /**
     * Prélève les dettes en cours d'un utilisateur sur ses fonds disponibles.
     * Appelé après chaque crédit de son portefeuille (vente, course).
     */
    public function recover(User $user): int
    {
        $total = 0;

        $debts = OrderDisputeDebt::where('user_id', $user->id)
            ->where('statut', OrderDisputeDebt::STATUT_EN_COURS)
            ->orderBy('id')
            ->get();

        foreach ($debts as $debt) {
            $total += DB::transaction(function () use ($debt, $user): int {
                $debt = OrderDisputeDebt::lockForUpdate()->find($debt->id);
                if (! $debt || ! $debt->isOpen()) {
                    return 0;
                }

                $walletType = WalletType::from($debt->wallet_type);
                $amount = min($debt->remaining(), $this->availableBalance($user, $walletType));
                if ($amount <= 0) {
                    return 0;
                }

                $this->moveToPlatform($user, $walletType, $amount, "Recouvrement de la dette du litige de la commande #{$debt->dispute->order_id}", [
                    'order_id' => $debt->dispute->order_id,
                    'order_dispute_debt_id' => $debt->id,
                    'type' => 'litige_commande_recouvrement',
                ]);

                $this->register($debt, $amount, 'prelevement_gains', null);

                return $amount;
            });
        }

        return $total;
    }

    /**
     * Règlement direct confirmé (Wave, Orange Money). Idempotent : une
     * transaction ne solde la dette qu'une fois.
     */
    public function applyPayment(Transaction $payment): bool
    {
        if (($payment->metadata['payment_type'] ?? '') !== 'dispute_debt' || ! $payment->statut->isSuccessful()) {
            return false;
        }

        return DB::transaction(function () use ($payment): bool {
            $debt = OrderDisputeDebt::lockForUpdate()->find($payment->metadata['debt_id'] ?? null);

            if (! $debt || (int) $debt->user_id !== (int) $payment->user_id) {
                Log::warning("[Dette de litige] Paiement #{$payment->id} sans dette correspondante.");

                return false;
            }

            if (OrderDisputeDebtEntry::where('order_dispute_debt_id', $debt->id)->where('transaction_id', $payment->id)->exists()) {
                return false;
            }

            if (! $debt->isOpen()) {
                Log::warning("[Dette de litige] Paiement #{$payment->id} reçu pour la dette #{$debt->id} déjà close : à rembourser manuellement.");

                return false;
            }

            // L'argent arrive de l'opérateur : il revient à ProsArtisan, qui avait avancé le remboursement.
            $amount = min((int) $payment->montant, $debt->remaining());
            $this->creditPlatform($amount, "Règlement de la dette du litige de la commande #{$debt->dispute->order_id}", [
                'order_id' => $debt->dispute->order_id,
                'order_dispute_debt_id' => $debt->id,
                'transaction_id' => $payment->id,
                'type' => 'litige_commande_reglement',
            ]);

            $this->register($debt, $amount, 'reglement_direct', $payment->id);

            return true;
        });
    }

    public function cancel(OrderDisputeDebt $debt, User $admin, string $reason): OrderDisputeDebt
    {
        if (! $debt->isOpen()) {
            throw ValidationException::withMessages(['debt' => ["Cette dette n'est plus en cours."]]);
        }

        $debt->update([
            'statut' => OrderDisputeDebt::STATUT_ANNULEE,
            'cancelled_by' => $admin->id,
            'cancel_reason' => $reason,
        ]);

        $this->audit->log('order_dispute_debt.cancelled', $debt, [
            'user_id' => $debt->user_id,
            'restant' => $debt->remaining(),
            'motif' => $reason,
        ], subjectLabel: 'Dette de litige #'.$debt->id, actor: $admin);

        if ($debt->user) {
            $this->notifications->notify($debt->user, 'litige_commande.dette_annulee.responsable', [
                'commande' => $debt->dispute->order_id,
            ], ['debt_id' => $debt->id]);
        }

        return $debt->fresh();
    }

    /**
     * Dettes d'un utilisateur, pour son application.
     *
     * @return list<array<string, mixed>>
     */
    public function listFor(User $user): array
    {
        return OrderDisputeDebt::with(['dispute', 'entries'])
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN statut = 'en_cours' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (OrderDisputeDebt $debt) => $this->present($debt))
            ->all();
    }

    /**
     * Dettes pour le backoffice, avec le débiteur.
     *
     * @return array{stats: array<string, int>, debts: Collection<int, array<string, mixed>>}
     */
    public function adminOverview(): array
    {
        $debts = OrderDisputeDebt::with(['dispute', 'entries', 'user:id,name,phone,role'])
            ->orderByRaw("CASE WHEN statut = 'en_cours' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(100)
            ->get();

        $open = OrderDisputeDebt::where('statut', OrderDisputeDebt::STATUT_EN_COURS);

        return [
            'stats' => [
                'en_cours' => (clone $open)->count(),
                'restant' => (int) (clone $open)->sum('montant') - (int) (clone $open)->sum('montant_recouvre'),
                'recouvre' => (int) OrderDisputeDebt::sum('montant_recouvre'),
            ],
            'debts' => $debts->map(fn (OrderDisputeDebt $debt) => $this->present($debt) + [
                'user' => $debt->user ? [
                    'id' => $debt->user->id,
                    'name' => $debt->user->name,
                    'phone' => $debt->user->phone,
                    'role' => $debt->user->role,
                ] : null,
                'cancel_reason' => $debt->cancel_reason,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(OrderDisputeDebt $debt): array
    {
        return [
            'id' => $debt->id,
            'order_id' => $debt->dispute?->order_id,
            'montant' => (int) $debt->montant,
            'montant_recouvre' => (int) $debt->montant_recouvre,
            'restant' => $debt->remaining(),
            'statut' => $debt->statut,
            'statut_label' => $debt->statutLabel(),
            'created_at' => $debt->created_at?->toIso8601String(),
            'settled_at' => $debt->settled_at?->toIso8601String(),
            'entries' => $debt->entries->map(fn (OrderDisputeDebtEntry $entry) => [
                'id' => $entry->id,
                'montant' => (int) $entry->montant,
                'source' => $entry->source,
                'source_label' => OrderDisputeDebt::SOURCE_LABELS[$entry->source] ?? $entry->source,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Compte financier de ProsArtisan : il porte l'avance du remboursement.
     */
    public function platformAccount(): User
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();

        if (! $admin) {
            throw ValidationException::withMessages(['order' => ['Aucun compte financier ProsArtisan pour avancer le remboursement.']]);
        }

        return $admin;
    }

    public function creditPlatform(int $amount, string $description, array $metadata): void
    {
        if ($amount > 0) {
            $this->wallets->credit($this->platformAccount(), WalletType::WALLET_MO, $amount, $description, $metadata);
        }
    }

    private function moveToPlatform(User $from, WalletType $walletType, int $amount, string $description, array $metadata): void
    {
        $this->wallets->debit($from, $walletType, $amount, $description, $metadata);
        $this->creditPlatform($amount, $description, $metadata + ['debiteur_id' => $from->id]);
    }

    private function register(OrderDisputeDebt $debt, int $amount, string $source, ?int $transactionId): void
    {
        OrderDisputeDebtEntry::create([
            'order_dispute_debt_id' => $debt->id,
            'montant' => $amount,
            'source' => $source,
            'transaction_id' => $transactionId,
            'created_at' => now(),
        ]);

        $recovered = (int) $debt->montant_recouvre + $amount;
        $settled = $recovered >= (int) $debt->montant;

        $debt->update([
            'montant_recouvre' => $recovered,
            'statut' => $settled ? OrderDisputeDebt::STATUT_SOLDEE : OrderDisputeDebt::STATUT_EN_COURS,
            'settled_at' => $settled ? now() : null,
        ]);

        if (! $debt->user) {
            return;
        }

        if ($settled) {
            $this->notifications->notify($debt->user, 'litige_commande.dette_soldee.responsable', [
                'commande' => $debt->dispute->order_id,
            ], ['debt_id' => $debt->id]);

            return;
        }

        $this->notifications->notify($debt->user, 'litige_commande.dette_prelevee.responsable', [
            'commande' => $debt->dispute->order_id,
            'montant' => $this->fcfa($amount),
            'restant' => $this->fcfa($debt->remaining()),
        ], ['debt_id' => $debt->id, 'action' => 'pay_dispute_debt']);
    }

    private function fcfa(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }
}
