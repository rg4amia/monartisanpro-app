<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPermissionRequest;
use App\Http\Requests\Admin\RevokePermissionRequest;
use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;

class AdminRolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService) {}

    /**
     * Obtenir la cartographie des rôles et de leurs permissions.
     */
    public function index(): JsonResponse
    {
        $roles = ['client', 'artisan', 'fournisseur', 'referent', 'admin'];
        $data = [];

        foreach ($roles as $role) {
            $data[$role] = $this->rolePermissionService->getRolePermissions($role);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Liste toutes les permissions disponibles.
     */
    public function listPermissions(): JsonResponse
    {
        $permissions = $this->rolePermissionService->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Assigner une action/permission à un rôle.
     */
    public function assign(AssignPermissionRequest $request): JsonResponse
    {
        $this->rolePermissionService->assignPermissionToRole(
            $request->validated('role'),
            $request->validated('permission')
        );

        return response()->json([
            'success' => true,
            'message' => 'Action attribuée au rôle avec succès.',
        ]);
    }

    /**
     * Révoquer une action/permission d'un rôle.
     */
    public function revoke(RevokePermissionRequest $request): JsonResponse
    {
        $this->rolePermissionService->revokePermissionFromRole(
            $request->validated('role'),
            $request->validated('permission')
        );

        return response()->json([
            'success' => true,
            'message' => 'Action retirée du rôle avec succès.',
        ]);
    }
}
