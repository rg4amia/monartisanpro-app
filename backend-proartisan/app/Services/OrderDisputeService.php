<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Litiges de commande : enregistrement à l'ouverture, clôture par un
 * administrateur avec son issue, historique par acteur (Chantier 21).
 *
 * La clôture consigne la décision et remet la commande à l'état « livrée ».
 * Elle ne déplace aucun fonds : un remboursement ou un dédommagement se
 * traite à part, et la note de clôture en garde la trace.
 */
class OrderDisputeService
{
    public function __construct(
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
    ) {}

    public function record(Order $order, User $openedBy, string $reason): OrderDispute
    {
        return OrderDispute::create([
            'order_id' => $order->id,
            'opened_by' => $openedBy->id,
            'reason' => $reason,
            'statut' => OrderDispute::STATUT_OUVERT,
            'opened_at' => now(),
        ]);
    }

    public function resolve(Order $order, User $admin, string $outcome, string $note): OrderDispute
    {
        if (! in_array($outcome, OrderDispute::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => ['Issue inconnue.']]);
        }

        $dispute = DB::transaction(function () use ($order, $admin, $outcome, $note): OrderDispute {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'disputed') {
                throw ValidationException::withMessages(['order' => ["Cette commande n'est pas en litige."]]);
            }

            // Litige ouvert avant ce chantier et non repris : la ligne est créée à la clôture.
            $dispute = OrderDispute::where('order_id', $order->id)
                ->where('statut', OrderDispute::STATUT_OUVERT)
                ->latest('id')
                ->first()
                ?? new OrderDispute([
                    'order_id' => $order->id,
                    'opened_by' => $order->client_id,
                    'reason' => $order->dispute_reason,
                    'opened_at' => $order->dispute_opened_at ?? now(),
                ]);

            $dispute->fill([
                'statut' => OrderDispute::STATUT_RESOLU,
                'outcome' => $outcome,
                'resolution_note' => $note,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();

            // Le litige ne s'ouvre que sur une commande livrée : elle le redevient.
            $order->update(['status' => 'delivered']);

            return $dispute;
        });

        $this->audit->log('order_dispute.resolved', $order, [
            'dispute_id' => $dispute->id,
            'outcome' => $outcome,
            'note' => $note,
        ], subjectLabel: 'Commande #'.$order->id, actor: $admin);

        $order->loadMissing(['client', 'supplier', 'driver']);
        $vars = ['commande' => $order->id, 'issue' => $dispute->outcomeLabel()];
        $data = ['order_id' => $order->id];

        if ($order->client) {
            $this->notifications->notify($order->client, 'commande.litige_clos.client', $vars, $data);
        }
        if ($order->supplier) {
            $this->notifications->notify($order->supplier, 'commande.litige_clos.fournisseur', $vars, $data);
        }
        if ($order->driver) {
            $this->notifications->notify($order->driver, 'commande.litige_clos.livreur', $vars, $data);
        }

        return $dispute;
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
        ]);

        return $page;
    }
}
