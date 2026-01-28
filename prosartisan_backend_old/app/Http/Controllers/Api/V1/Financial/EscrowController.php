<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Application\UseCases\Financial\BlockEscrowFunds\BlockEscrowFundsCommand;
use App\Application\UseCases\Financial\BlockEscrowFunds\BlockEscrowFundsHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BlockEscrowRequest;
use App\Http\Resources\Financial\SequestreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Escrow Controller for Financial Transactions
 *
 * Handles escrow fund blocking and management operations.
 *
 * Requirements: 4.1, 4.2
 */
class EscrowController extends Controller
{
    private BlockEscrowFundsHandler $blockEscrowHandler;

    public function __construct(BlockEscrowFundsHandler $blockEscrowHandler)
    {
        $this->blockEscrowHandler = $blockEscrowHandler;
    }

    /**
     * Block funds in escrow after quote acceptance
     *
     * POST /api/v1/escrow/block
     */
    public function block(BlockEscrowRequest $request): JsonResponse
    {
        try {
            $command = new BlockEscrowFundsCommand(
                $request->validated('mission_id'),
                $request->validated('devis_id'),
                $request->validated('client_id'),
                $request->validated('artisan_id'),
                $request->validated('total_amount_centimes')
            );

            $sequestre = $this->blockEscrowHandler->handle($command);

            Log::info('Escrow funds blocked successfully', [
                'sequestre_id' => $sequestre->getId()->getValue(),
                'mission_id' => $request->validated('mission_id'),
                'total_amount' => $request->validated('total_amount_centimes'),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Funds blocked in escrow successfully',
                'data' => new SequestreResource($sequestre),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to block escrow funds', [
                'error' => $e->getMessage(),
                'mission_id' => $request->validated('mission_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to block funds in escrow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
