<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommunicationRequest;
use App\Models\Communication;
use App\Services\CommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function __construct(private CommunicationService $communicationService) {}

    /**
     * Liste des communications (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $communications = $this->communicationService->list(
            $request->query('type'),
            $request->query('statut'),
            $perPage,
        );

        return response()->json([
            'success' => true,
            'data'    => $communications->items(),
            'meta'    => [
                'total'        => $communications->total(),
                'current_page' => $communications->currentPage(),
                'last_page'    => $communications->lastPage(),
            ],
        ]);
    }

    /**
     * Créer une communication (admin).
     */
    public function store(StoreCommunicationRequest $request): JsonResponse
    {
        $communication = $this->communicationService->store(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Communication créée en brouillon.',
            'data'    => $communication->load('auteur:id,name,phone'),
        ], 201);
    }

    /**
     * Afficher une communication.
     */
    public function show(Communication $communication): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $communication->load('auteur:id,name,phone'),
        ]);
    }

    /**
     * Modifier une communication (brouillon uniquement).
     */
    public function update(StoreCommunicationRequest $request, Communication $communication): JsonResponse
    {
        try {
            $updated = $this->communicationService->update(
                $communication,
                $request->validated(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Communication modifiée.',
                'data'    => $updated->load('auteur:id,name,phone'),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Publier une communication (brouillon → publié).
     */
    public function publish(Communication $communication): JsonResponse
    {
        try {
            $published = $this->communicationService->publish($communication);

            return response()->json([
                'success' => true,
                'message' => 'Communication publiée avec succès.',
                'data'    => $published->load('auteur:id,name,phone'),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Clôturer une communication (publié → clôturé).
     */
    public function cloturer(Communication $communication): JsonResponse
    {
        try {
            $cloturee = $this->communicationService->cloturer($communication);

            return response()->json([
                'success' => true,
                'message' => 'Communication clôturée.',
                'data'    => $cloturee->load('auteur:id,name,phone'),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Supprimer une communication (brouillon uniquement).
     */
    public function destroy(Communication $communication): JsonResponse
    {
        try {
            $this->communicationService->destroy($communication);

            return response()->json([
                'success' => true,
                'message' => 'Communication supprimée.',
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Communications actives pour l'utilisateur connecté (app mobile).
     */
    public function activeForUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        // Mapper le rôle "driver" vers "livreur" si nécessaire
        if ($role === 'driver') {
            $role = 'livreur';
        }

        $data = $this->communicationService->getActiveForRole($role);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
