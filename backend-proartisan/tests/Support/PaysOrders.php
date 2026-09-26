<?php

namespace Tests\Support;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Règle une commande de matériaux comme le ferait l'opérateur (Chantier 11) :
 * la commande est créée `pending` et n'est `paid` qu'une fois le paiement
 * confirmé par la voie commune `PaymentService::applyConfirmedPayment`.
 */
trait PaysOrders
{
    protected function payOrder(Order $order): Transaction
    {
        $orders = app(OrderService::class)->ordersPaidTogether($order)
            ->where('status', OrderService::STATUS_PENDING_PAYMENT)
            ->values();

        $transaction = Transaction::create([
            'user_id' => $order->client_id,
            'type' => 'acompte',
            'montant' => (int) $orders->sum('total_amount'),
            'wallet_source' => 'client_mobile_money_'.$order->client_id,
            'wallet_dest' => $order->order_group_id ? 'escrow_group_'.$order->order_group_id : 'escrow_order_'.$order->id,
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => [
                'payment_type' => 'order',
                'order_ids' => $orders->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ],
        ]);

        app(PaymentService::class)->applyConfirmedPayment($transaction);

        return $transaction;
    }
}
