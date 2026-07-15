<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ArtisanProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function update(Request $request, User $user): JsonResponse
    {
        //$this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'fcm_token' => ['sometimes', 'nullable', 'string'],
            'intervention_nuit' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('intervention_nuit', $data)) {
            if ($user->role !== 'artisan') {
                throw ValidationException::withMessages([
                    'intervention_nuit' => ['Seuls les artisans peuvent activer le mode intervention de nuit.'],
                ]);
            }

            ArtisanProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['intervient_la_nuit' => false]
            )->update([
                'intervient_la_nuit' => (bool) $data['intervention_nuit'],
            ]);

            unset($data['intervention_nuit']);
        }

        if ($data !== []) {
            $user->update($data);
        }

        $user = $user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade');

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ]);
    }

    public function updateLocation(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            abort(403, 'Accès refusé.');
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'lat.required' => 'La latitude est obligatoire.',
            'lng.required' => 'La longitude est obligatoire.',
        ]);

        $user->setPosition((float) $data['lat'], (float) $data['lng']);

        if ($user->role === 'fournisseur') {
            $fournisseur = $user->fournisseurAgree;
            if ($fournisseur) {
                $fournisseur->setPosition((float) $data['lat'], (float) $data['lng']);
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Position mise à jour.',
            'position' => $data,
        ]);
    }

    public function setRole(Request $request, User $user): JsonResponse
    {
        //$this->authorize('update', $user);

        $data = $request->validate([
            'role' => ['required', 'in:client,artisan,fournisseur'],
        ], [
            'role.required' => 'Le rôle est obligatoire.',
            'role.in'       => 'Rôle invalide.',
        ]);

        $user->update(['role' => $data['role']]);

        if ($data['role'] === 'artisan') {
            ArtisanProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['intervient_la_nuit' => false]
            );
        }

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }
}
