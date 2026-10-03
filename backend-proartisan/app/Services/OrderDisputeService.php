<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\WalletType;
use App\Models\MobileMoneyPayout;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Litiges de commande (Chantiers 21 et 22).
 *
 * - Ouverture : la ligne d'historique est créée et le montant crédité au
 *   fournisseur pour cette commande est gelé (il ne peut plus le retirer).
 * - Clôture par un administrateur : réclamation rejetée (le gel est levé) ou
 *   acceptée — le client est remboursé du montant fixé par l'administrateur,
 *   plafonné au prix des articles, et le responsable désigné (fournisseur ou
 *   livreur) supporte la somme. ProsArtisan avance le remboursement ; ce que
 *   le portefeuille du responsable ne couvre pas devient une dette
 *   (`OrderDisputeDebtService`).
 *
 * Les frais de service payés par le client ne sont jamais remboursés.
 */
class OrderDisputeService
{
    public const RESPONSIBLES = ['fournisseur', 'livreur'];

    public function __construct(
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
        private OrderDisputeDebtService $debts,
        private MobileMoneyPayoutService $payouts,
    ) {}

    public function record(Order $order, User $openedBy, string $reason): OrderDispute
    {
        // Gel : ce que le fournisseur a reçu pour cette commande, dans la
        // limite de ce que son portefeuille contient encore. La part déjà
        // retirée sera recouvrée à la clôture si la réclamation est acceptée.
        $supplier = $order->supplier;
        $frozen = $supplier
            ? min($this->supplierNet($order, (int) $order->subtotal), $this->debts->availableBalance($supplier, WalletType::WALLET_MATERIAUX))
            : 0;

        return OrderDispute::create([
            'order_id' => $order->id,
            'opened_by' => $openedBy->id,
            'reason' => $reason,
            'statut' => OrderDispute::STATUT_OUVERT,
            'opened_at' => now(),
            'frozen_amount' => $frozen,
            'frozen_user_id' => $frozen > 0 ? $supplier->id : null,
        ]);
    }

    /**
     * @param  array{refund_amount?: int|null, responsible?: string|null}  $decision
     */
    public function resolve(Order $order, User $admin, string $outcome, string $note, array $decision = []): OrderDispute
    {
        if (! in_array($outcome, OrderDispute::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => ['Issue inconnue.']]);
        }

        $accepted = $outcome === 'reclamation_acceptee';

        $dispute = DB::transaction(function () use ($order, $admin, $outcome, $note, $decision, $accepted): OrderDispute {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'disputed') {
                throw ValidationException::withMessages(['order' => ["Cette commande n'est pas en litige."]]);
            }

            // Litige ouvert avant le Chantier 21 et non repris : la ligne est créée à la clôture.
            $dispute = OrderDispute::where('order_id', $order->id)
                ->where('statut', OrderDispute::STATUT_OUVERT)
                ->latest('id')
                ->first()
                ?? OrderDispute::create([
                    'order_id' => $order->id,
                    'opened_by' => $order->client_id,
                    'reason' => $order->dispute_reason,
                    'statut' => OrderDispute::STATUT_OUVERT,
                    'opened_at' => $order->dispute_opened_at ?? now(),
                ]);

            // Le litige est clos avant tout mouvement : son gel ne compte plus
            // dans les fonds disponibles du fournisseur.
            $dispute->fill([
                'statut' => OrderDispute::STATUT_RESOLU,
                'outcome' => $outcome,
                'resolution_note' => $note,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();

            if ($accepted) {
                $this->refund($order, $dispute, $decision);
            }

            // Le litige ne s'ouvre que sur une commande livrée : elle le redevient.
            $order->update(['status' => 'delivered']);

            return $dispute;
        });

        $this->audit->log('order_dispute.resolved', $order, [
            'dispute_id' => $dispute->id,
            'outcome' => $outcome,
            'note' => $note,
            'refund_amount' => (int) $dispute->refund_amount,
            'fare_refund' => (int) $dispute->fare_refund,
            'responsible' => $dispute->responsible_role,
        ], subjectLabel: 'Commande #'.$order->id, actor: $admin);

        $this->notifyResolution($order, $dispute, $accepted);

        return $dispute;
    }

    /**
     * Remboursement du client et prise en charge par le responsable. Le
     * montant et son plafond sont établis ici, jamais par l'appelant.
     *
     * @param  array{refund_amount?: int|null, responsible?: string|null}  $decision
     */
    private function refund(Order $order, OrderDispute $dispute, array $decision): void
    {
        $refund = (int) ($decision['refund_amount'] ?? 0);
        $role = (string) ($decision['responsible'] ?? '');
        $subtotal = (int) $order->subtotal;

        if ($refund < 1 || $refund > $subtotal) {
            throw ValidationException::withMessages([
                'refund_amount' => ['Le remboursement doit être compris entre 1 FCFA et le prix des articles ('.number_format($subtotal, 0, ',', ' ').' FCFA).'],
            ]);
        }

        if (! in_array($role, self::RESPONSIBLES, true)) {
            throw ValidationException::withMessages(['responsible' => ['Désignez le responsable : fournisseur ou livreur.']]);
        }

        $responsible = $role === 'livreur' ? $order->driver : $order->supplier;
        if (! $responsible) {
            throw ValidationException::withMessages(['responsible' => ["Cette commande n'a pas de livreur : le responsable ne peut être que le fournisseur."]]);
        }

        $client = $order->client;
        if (! $client) {
            throw ValidationException::withMessages(['order' => ['Le client de cette commande est introuvable.']]);
        }

        // Ce que supporte le responsable.
        // - Fournisseur : il n'a reçu que le prix net de commission ; ProsArtisan rend sa commission.
        // - Livreur : la marchandise perdue ou abîmée lui est imputée en totalité.
        $charge = $role === 'fournisseur' ? $this->supplierNet($order, $refund) : $refund;
        $fareRefund = 0;

        // Course : la faute du livreur l'annule ou la rembourse.
        if ($role === 'livreur') {
            if ($order->delivery_fare_status === 'a_payer') {
                $due = $order->deliveryFareDue();
                $order->update([
                    'delivery_fare_status' => 'annulee',
                    'total_amount' => max(0, (int) $order->total_amount - $due),
                ]);
                app(DeliveryFareCollectionService::class)->liftIfSettled($client->fresh());
            } elseif ($order->delivery_fare_status === 'paye' && $order->deliveryFareTotal() > 0) {
                $fareRefund = $order->deliveryFareTotal();
                $order->update(['delivery_fare_status' => 'remboursee']);
                // Le livreur rend son gain net ; ProsArtisan rend sa commission.
                $charge += app(OrderService::class)->driverShare($fareRefund)['net'];
            }
        }

        $walletType = $role === 'fournisseur' ? WalletType::WALLET_MATERIAUX : WalletType::WALLET_MO;
        $this->debts->charge($dispute, $responsible, $walletType, $charge);

        // ProsArtisan avance le remboursement : il part de son compte
        // financier, débité au seul virement réussi (Règle d'or 74).
        $total = $refund + $fareRefund;
        $platform = $this->debts->platformAccount();

        $payout = $this->payouts->dispatch(
            $client,
            WalletType::WALLET_MO,
            $total,
            MobileMoneyPayout::CONTEXT_REMBOURSEMENT_CLIENT,
            $this->clientProvider($order, $client),
            "Remboursement du litige de la commande #{$order->id}",
            [
                'type' => 'remboursement',
                'wallet_source' => 'escrow_order_'.$order->id,
                'wallet_dest' => 'client_mobile_money_'.$client->id,
                'metadata' => [
                    'origine' => 'litige_commande',
                    'order_id' => $order->id,
                    'order_dispute_id' => $dispute->id,
                    'refund_articles' => $refund,
                    'refund_course' => $fareRefund,
                    'description' => "Remboursement du litige de la commande #{$order->id}",
                ],
            ],
            ['order_id' => $order->id, 'order_dispute_id' => $dispute->id, 'type' => 'litige_commande_remboursement'],
            source: $platform,
            debits: [['wallet_type' => WalletType::WALLET_MO, 'montant' => $total]],
        );

        $dispute->update([
            'refund_amount' => $refund,
            'fare_refund' => $fareRefund,
            'responsible_role' => $role,
            'responsible_user_id' => $responsible->id,
            'refund_payout_id' => $payout->id,
        ]);
    }

    /**
     * Part d'un montant d'articles réellement reçue par le fournisseur : le
     * prix moins la commission prélevée au retrait de la commande.
     */
    private function supplierNet(Order $order, int $amount): int
    {
        $subtotal = (int) $order->subtotal;
        if ($subtotal <= 0 || $amount <= 0) {
            return 0;
        }

        $release = Transaction::where('type', 'paiement_fournisseur')
            ->where('wallet_source', 'escrow_order_'.$order->id)
            ->latest('id')
            ->first();

        $commission = $release
            ? (int) ($release->metadata['supplier_commission'] ?? 0)
            : (int) round($subtotal * (float) Setting::getValueByKey('commission_fournisseur', 0.05));

        return $amount - (int) round($amount * $commission / $subtotal);
    }

    /**
     * Opérateur du remboursement : celui du paiement de la commande, à défaut Wave.
     */
    private function clientProvider(Order $order, User $client): string
    {
        $payment = Transaction::where('user_id', $client->id)
            ->whereIn('type', ['acompte', 'paiement_livraison'])
            ->whereJsonContains('metadata->order_id', $order->id)
            ->latest('id')
            ->first();

        $provider = $payment?->provider instanceof PaymentProvider ? $payment->provider->value : (string) ($payment?->provider ?? '');

        return in_array($provider, ['wave', 'orange_money'], true) ? $provider : 'wave';
    }

    private function notifyResolution(Order $order, OrderDispute $dispute, bool $accepted): void
    {
        $order->loadMissing(['client', 'supplier', 'driver']);
        $data = ['order_id' => $order->id];

        if ($order->client) {
            if ($accepted) {
                $this->notifications->notify($order->client, 'commande.litige_rembourse.client', [
                    'commande' => $order->id,
                    'montant' => number_format((int) $dispute->refund_amount + (int) $dispute->fare_refund, 0, ',', ' ').' FCFA',
                ], $data);
            } else {
                $this->notifications->notify($order->client, 'commande.litige_clos.client', [
                    'commande' => $order->id, 'issue' => $dispute->outcomeLabel(),
                ], $data);
            }
        }

        $vars = ['commande' => $order->id, 'issue' => $dispute->outcomeLabel()];

        if ($order->supplier) {
            $this->notifications->notify($order->supplier, 'commande.litige_clos.fournisseur', $vars, $data);
        }
        if ($order->driver) {
            $this->notifications->notify($order->driver, 'commande.litige_clos.livreur', $vars, $data);
        }
    }

    /**
     * Litiges des commandes de l'utilisateur : celles qu'il a passées,
     * vendues ou livrées. Aucun nom ni téléphone n'est renvoyé.
     */
    public function paginateFor(User $user, ?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        $column = match ($user->role) {
            'client' => 'client_id',
            'fournisseur' => 'supplier_id',
            'livreur' => 'driver_id',
            default => null,
        };

        if ($column === null) {
            throw ValidationException::withMessages(['role' => ['Ce profil ne passe, ne vend ni ne livre de commandes.']]);
        }

        $page = OrderDispute::query()
            ->with('order')
            ->whereHas('order', fn ($q) => $q->where($column, $user->id))
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('opened_at')
            ->paginate($perPage);

        $page->getCollection()->transform(fn (OrderDispute $dispute) => [
            'id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'reason' => $dispute->reason,
            'statut' => $dispute->statut,
            'statut_label' => $dispute->statutLabel(),
            'outcome' => $dispute->outcome,
            'outcome_label' => $dispute->outcomeLabel(),
            'resolution_note' => $dispute->resolution_note,
            'opened_at' => $dispute->opened_at?->toIso8601String(),
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            'order_total' => (int) ($dispute->order?->subtotal ?? 0),
            // Montant rendu au client (articles et, le cas échéant, course).
            'refund_amount' => (int) $dispute->refund_amount + (int) $dispute->fare_refund,
            'responsible_label' => $dispute->responsibleLabel(),
        ]);

        return $page;
    }
}
