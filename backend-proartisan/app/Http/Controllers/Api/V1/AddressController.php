<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(private AddressService $addressService)
    {
    }

    /**
     * Carnet d'adresses du client connecté.
     * GET /api/v1/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->listForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => AddressResource::collection($addresses),
        ]);
    }

    /**
     * Ajouter une adresse au carnet.
     * POST /api/v1/addresses
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Adresse enregistrée avec succès.',
            'data' => new AddressResource($address),
        ], 201);
    }

    /**
     * Modifier une adresse du carnet.
     * PUT /api/v1/addresses/{address}
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $address = $this->addressService->update($address, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Adresse mise à jour avec succès.',
            'data' => new AddressResource($address),
        ]);
    }

    /**
     * Supprimer une adresse du carnet.
     * DELETE /api/v1/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $this->addressService->delete($address);

        return response()->json([
            'success' => true,
            'message' => 'Adresse supprimée avec succès.',
        ]);
    }

    /**
     * Définir une adresse comme adresse par défaut.
     * POST /api/v1/addresses/{address}/default
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $address = $this->addressService->setDefault($address);

        return response()->json([
            'success' => true,
            'message' => 'Adresse par défaut mise à jour.',
            'data' => new AddressResource($address),
        ]);
    }
}
