<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'min:2', 'max:100'],
            'fcm_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $user->update($data);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user->fresh()),
        ]);
    }

    public function updateLocation(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'lat.required' => 'La latitude est obligatoire.',
            'lng.required' => 'La longitude est obligatoire.',
        ]);

        $user->setPosition((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'success'  => true,
            'message'  => 'Position mise à jour.',
            'position' => $data,
        ]);
    }

    public function setRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'role' => ['required', 'in:client,artisan,fournisseur'],
        ], [
            'role.required' => 'Le rôle est obligatoire.',
            'role.in'       => 'Rôle invalide.',
        ]);

        $user->update(['role' => $data['role']]);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user->fresh()),
        ]);
    }
}
