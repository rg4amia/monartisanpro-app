<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPermissionRequest;
use App\Http\Requests\Admin\RevokePermissionRequest;
use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService) {}

    /**
     * Obtenir la cartographie des rôles et de leurs permissions.
     */
    public function index(): JsonResponse
    {
        $data = [];

        foreach (RolePermissionService::ROLES as $role) {
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
    public function assign(AssignPermissionRequest $request): JsonResponse|RedirectResponse
    {
        $this->rolePermissionService->assignPermissionToRole(
            $request->validated('role'),
            $request->validated('permission')
        );

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Action attribuée au rôle avec succès.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Action attribuée au rôle avec succès.',
        ]);
    }

    /**
     * Rendre à un rôle ses droits d'origine.
     */
    public function reset(Request $request): JsonResponse|RedirectResponse
    {
        $role = $request->validate([
            'role' => ['required', 'string', Rule::in(RolePermissionService::ROLES)],
        ], [
            'role.required' => 'Le rôle est requis.',
            'role.in' => 'Le rôle spécifié est invalide.',
        ])['role'];

        $this->rolePermissionService->resetRole($role);

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Droits d\'origine du rôle rétablis.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Droits d\'origine du rôle rétablis.',
        ]);
    }

    /**
     * Révoquer une action/permission d'un rôle.
     */
    public function revoke(RevokePermissionRequest $request): JsonResponse|RedirectResponse
    {
        $this->rolePermissionService->revokePermissionFromRole(
            $request->validated('role'),
            $request->validated('permission')
        );

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Action retirée du rôle avec succès.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Action retirée du rôle avec succès.',
        ]);
    }
}
