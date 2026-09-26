<?php

namespace App\Http\Middleware;

use App\Services\DeliveryFareCollectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse une nouvelle commande ou mission à un client restreint pour course
 * impayée (Chantier 11). Contrairement au blocage de compte
 * (`account.active`), il laisse passer le paiement de ce qu'il doit.
 */
class PaymentUnrestricted
{
    public function __construct(private DeliveryFareCollectionService $collection) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPaymentRestricted()) {
            return $next($request);
        }

        $orderIds = $this->collection->unpaidOrderIds($user);
        $orders = $orderIds === [] ? '' : ' (commande #'.implode(', #', $orderIds).')';

        return response()->json([
            'success' => false,
            'message' => "Votre compte est restreint : une course livrée reste impayée{$orders}. Réglez-la depuis « Mes commandes » pour commander ou publier à nouveau.",
            'payment_restricted' => true,
            'unpaid_order_ids' => $orderIds,
            'reason' => $user->payment_restriction_reason,
        ], 403);
    }
}
