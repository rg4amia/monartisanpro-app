<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Application\UseCases\Financial\GetTransactionHistory\GetTransactionHistoryQuery;
use App\Application\UseCases\Financial\GetTransactionHistory\GetTransactionHistoryHandler;
use App\Http\Resources\Financial\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Controller for Financial History
 *
 * Handles transaction history and audit trail operations.
 *
 * Requirements: 4.6, 13.6
 */
class TransactionController extends Controller
{
    private GetTransactionHistoryHandler $getTransactionHistoryHandler;

    public function __construct(GetTransactionHistoryHandler $getTransactionHistoryHandler)
    {
        $this->getTransactionHistoryHandler = $getTransactionHistoryHandler;
    }

    /**
     * Get transaction history for authenticated user
     *
     * GET /api/v1/transactions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $type = $request->get('type'); // Optional filter by transaction type

            $query = new GetTransactionHistoryQuery(
                $userId,
                $page,
                $limit,
                $type
            );

            $result = $this->getTransactionHistoryHandler->handle($query);

            Log::info('Transaction history retrieved', [
                'user_id' => $userId,
                'page' => $page,
                'count' => count($result['transactions'])
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transactions' => TransactionResource::collection($result['transactions']),
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $result['total'],
                        'last_page' => ceil($result['total'] / $limit)
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve transaction history', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transaction history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
