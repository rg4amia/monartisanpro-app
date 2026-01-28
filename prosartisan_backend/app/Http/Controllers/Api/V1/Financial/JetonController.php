<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Application\UseCases\Financial\GenerateJeton\GenerateJetonCommand;
use App\Application\UseCases\Financial\GenerateJeton\GenerateJetonHandler;
use App\Application\UseCases\Financial\ValidateJeton\ValidateJetonCommand;
use App\Application\UseCases\Financial\ValidateJeton\ValidateJetonHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\GenerateJetonRequest;
use App\Http\Requests\Financial\ValidateJetonRequest;
use App\Http\Resources\Financial\JetonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Jeton Controller for Material Token Management
 *
 * Handles jeton generation and validation operations.
 *
 * Requirements: 5.1, 5.3
 */
class JetonController extends Controller
{
    private GenerateJetonHandler $generateJetonHandler;

    private ValidateJetonHandler $validateJetonHandler;

    public function __construct(
        GenerateJetonHandler $generateJetonHandler,
        ValidateJetonHandler $validateJetonHandler
    ) {
        $this->generateJetonHandler = $generateJetonHandler;
        $this->validateJetonHandler = $validateJetonHandler;
    }

    /**
     * Generate a new jeton for materials purchase
     *
     * POST /api/v1/jetons/generate
     */
    public function generate(GenerateJetonRequest $request): JsonResponse
    {
        try {
            $command = new GenerateJetonCommand(
                $request->validated('sequestre_id'),
                $request->validated('artisan_id'),
                $request->validated('supplier_ids', [])
            );

            $jeton = $this->generateJetonHandler->handle($command);

            Log::info('Jeton generated successfully', [
                'jeton_id' => $jeton->getId()->getValue(),
                'jeton_code' => $jeton->getCode()->toString(),
                'artisan_id' => $request->validated('artisan_id'),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Jeton generated successfully',
                'data' => new JetonResource($jeton),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to generate jeton', [
                'error' => $e->getMessage(),
                'sequestre_id' => $request->validated('sequestre_id'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate jeton',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a jeton for materials purchase
     *
     * POST /api/v1/jetons/validate
     */
    public function validate(ValidateJetonRequest $request): JsonResponse
    {
        try {
            $command = new ValidateJetonCommand(
                $request->validated('jeton_code'),
                $request->validated('fournisseur_id'),
                $request->validated('amount_centimes'),
                $request->validated('artisan_latitude'),
                $request->validated('artisan_longitude'),
                $request->validated('supplier_latitude'),
                $request->validated('supplier_longitude')
            );

            $result = $this->validateJetonHandler->handle($command);

            Log::info('Jeton validated successfully', [
                'jeton_code' => $request->validated('jeton_code'),
                'fournisseur_id' => $request->validated('fournisseur_id'),
                'amount_used' => $request->validated('amount_centimes'),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Jeton validated successfully',
                'data' => [
                    'validation_id' => $result['validation_id'],
                    'amount_used' => $result['amount_used'],
                    'remaining_amount' => $result['remaining_amount'],
                    'validated_at' => $result['validated_at'],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to validate jeton', [
                'error' => $e->getMessage(),
                'jeton_code' => $request->validated('jeton_code'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to validate jeton',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
