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
            'sector_id' => ['sometimes', 'nullable', 'exists:sectors,id'],
            'trade_id' => [
                'sometimes', 'nullable', 'exists:trades,id',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $sectorId = $request->input('sector_id') ?? ($user->artisanProfile?->sector_id);
                    if ($value && $sectorId) {
                        $exists = \DB::table('trades')
                            ->where('id', $value)
                            ->where('sector_id', $sectorId)
                            ->exists();
                        if (!$exists) {
                            $fail('Le métier sélectionné doit appartenir au secteur d\'activité choisi.');
                        }
                    }
                }
            ],
            'bio' => ['sometimes', 'nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:60'],
        ]);

        if (array_key_exists('intervention_nuit', $data) || 
            array_key_exists('sector_id', $data) || 
            array_key_exists('trade_id', $data) || 
            array_key_exists('bio', $data) || 
            array_key_exists('experience_years', $data)) {
            
            if ($user->role !== 'artisan') {
                throw ValidationException::withMessages([
                    'role' => ['Seuls les artisans possèdent un profil métier modifiable.'],
                ]);
            }

            $artisanData = [];
            if (array_key_exists('intervention_nuit', $data)) {
                $artisanData['intervient_la_nuit'] = (bool) $data['intervention_nuit'];
                unset($data['intervention_nuit']);
            }
            if (array_key_exists('sector_id', $data)) {
                $artisanData['sector_id'] = $data['sector_id'];
                unset($data['sector_id']);
            }
            if (array_key_exists('trade_id', $data)) {
                $artisanData['trade_id'] = $data['trade_id'];
                unset($data['trade_id']);
            }
            if (array_key_exists('bio', $data)) {
                $artisanData['bio'] = $data['bio'];
                unset($data['bio']);
            }
            if (array_key_exists('experience_years', $data)) {
                $artisanData['experience_years'] = $data['experience_years'];
                unset($data['experience_years']);
            }

            ArtisanProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $artisanData
            );
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

    public function updateCnmci(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            abort(403, 'Accès refusé.');
        }

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les artisans peuvent s\'affilier à la CNMCI.',
            ], 422);
        }

        $data = $request->validate([
            'cnmci_number' => ['nullable', 'string', 'max:100'],
            'cnmci_card' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $updateData = [];

        if (array_key_exists('cnmci_number', $data)) {
            $updateData['cnmci_number'] = $data['cnmci_number'];
        }

        if ($request->hasFile('cnmci_card')) {
            $file = $request->file('cnmci_card');
            $path = $file->store('cnmci', 'public');
            $updateData['cnmci_card_url'] = '/storage/' . $path;
        }

        $numberVal = array_key_exists('cnmci_number', $updateData) ? $updateData['cnmci_number'] : $user->cnmci_number;
        $cardVal = array_key_exists('cnmci_card_url', $updateData) ? $updateData['cnmci_card_url'] : $user->cnmci_card_url;

        if (empty($numberVal) && empty($cardVal)) {
            $updateData['cnmci_status'] = 'non_renseigne';
        } else {
            if ($user->cnmci_status === 'non_renseigne' || $user->cnmci_status === 'rejete' || isset($updateData['cnmci_card_url']) || (isset($updateData['cnmci_number']) && $updateData['cnmci_number'] !== $user->cnmci_number)) {
                $updateData['cnmci_status'] = 'en_attente';
            }
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Informations CNMCI mises à jour, en attente de validation.',
            'data'    => new UserResource($user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            abort(403, 'Accès refusé.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Votre compte a été supprimé avec succès.',
        ]);
    }
}
