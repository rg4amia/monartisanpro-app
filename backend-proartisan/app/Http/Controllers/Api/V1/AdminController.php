<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Litige\ArbitrateLitigeRequest;
use App\Http\Requests\Admin\ReviewFournisseurRequest;
use App\Http\Requests\Admin\ReviewKycRequest;
use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->adminService->dashboard(),
        ]);
    }

    public function pendingKyc(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $role = $request->query('role');

        $users = $this->adminService->pendingKyc($role, $perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function reviewKyc(ReviewKycRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->adminService->reviewKyc(
            $request->user(),
            $user,
            $request->validated('decision'),
            $request->validated('rejection_reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Dossier KYC traité avec succès.',
            'data' => [
                'user_id' => $updatedUser->id,
                'kyc_status' => $updatedUser->kyc_status,
            ],
        ]);
    }

    public function missions(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $missions = $this->adminService->listMissions(
            $request->query('status'),
            $request->query('q'),
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $missions->items(),
            'meta' => [
                'total' => $missions->total(),
                'current_page' => $missions->currentPage(),
                'last_page' => $missions->lastPage(),
            ],
        ]);
    }

    public function litiges(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $statut = $request->query('statut');

        $litiges = $this->adminService->listLitiges($statut, $perPage);

        return response()->json([
            'success' => true,
            'data' => $litiges->items(),
            'meta' => [
                'total' => $litiges->total(),
                'current_page' => $litiges->currentPage(),
                'last_page' => $litiges->lastPage(),
            ],
        ]);
    }

    public function resolveLitige(ArbitrateLitigeRequest $request, Litige $litige): JsonResponse
    {
        $litige = $this->adminService->resolveLitige(
            $request->user(),
            $litige,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Litige arbitré avec succès.',
            'data' => $litige,
        ]);
    }

    public function pendingFournisseurs(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $fournisseurs = $this->adminService->pendingFournisseurs($perPage);

        return response()->json([
            'success' => true,
            'data' => $fournisseurs->items(),
            'meta' => [
                'total' => $fournisseurs->total(),
                'current_page' => $fournisseurs->currentPage(),
                'last_page' => $fournisseurs->lastPage(),
            ],
        ]);
    }

    public function reviewFournisseur(ReviewFournisseurRequest $request, FournisseurAgree $fournisseur): JsonResponse
    {
        $fournisseur = $this->adminService->reviewFournisseur(
            $request->user(),
            $fournisseur,
            $request->validated('decision')
        );

        return response()->json([
            'success' => true,
            'message' => 'Décision fournisseur enregistrée.',
            'data' => $fournisseur,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $users = $this->adminService->listUsers(
            $request->query('q'),
            $request->query('role'),
            $request->query('kyc_status'),
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $transactions = $this->adminService->listTransactions(
            $request->query('status'),
            $request->query('provider'),
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'total' => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
