<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $status = $request->query('status');

        $query = Transaction::where('user_id', $user->id)
            ->orWhereHas('mission', function ($q) use ($user) {
                $q->where('client_id', $user->id)->orWhere('artisan_id', $user->id);
            });

        if ($status) {
            $query->where('statut', $status);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'data'    => TransactionResource::collection($transactions->items()),
            'meta'    => [
                'total'        => $transactions->total(),
                'current_page' => $transactions->currentPage(),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'walletMateriaux' => $user->wallet_materiaux,
                'walletMo'        => $user->wallet_mo,
                'total'           => $user->wallet_materiaux + $user->wallet_mo,
            ],
        ]);
    }
}
