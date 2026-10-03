<?php

namespace App\Http\Middleware;

use App\Services\OrderDisputeDebtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les nouvelles activités et les retraits d'un fournisseur ou d'un
 * livreur qui doit une somme à ProsArtisan après un litige de commande
 * (Chantier 22). Le règlement de la dette, lui, reste accessible.
 */
class EnsureNoDisputeDebt
{
    public function __construct(private OrderDisputeDebtService $debts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->debts->hasOpenDebt($user)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Votre compte est bloqué : un remboursement reste dû à la suite d\'un litige de commande. Réglez-le depuis « Remboursement dû » pour reprendre votre activité.',
            'dispute_debt' => true,
        ], 403);
    }
}
