<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ArtisanProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminGdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private AdminGdprService $gdpr,
        private AdminActivityLogger $audit,
    ) {}

    public function update(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            abort(403, 'Accès refusé.');
        }

        $data = $request->validate([
            // Aligné sur la validation d'inscription (max:255) : `name` est
            // revalidé à chaque mise à jour de profil, une borne plus stricte
            // ici rejetterait des comptes déjà valides.
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'fcm_token' => ['sometimes', 'nullable', 'string'],
            'intervention_nuit' => ['sometimes', 'boolean'],
            'sector_id' => ['sometimes', 'nullable', 'exists:sectors,id'],
            'trade_id' => [
                'sometimes', 'nullable', 'exists:trades,id',
                function ($attribute, $value, $fail) use ($request, $user) {
                    $sectorId = $request->input('sector_id') ?? ($user->artisanProfile?->sector_id);
                    if ($value && $sectorId) {
                        $exists = Trade::query()
                            ->whereKey($value)
                            ->where('sector_id', $sectorId)
                            ->exists();
                        if (! $exists) {
                            $fail('Le métier sélectionné doit appartenir au secteur d\'activité choisi.');
                        }
                    }
                },
            ],
            'bio' => ['sometimes', 'nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'payment_phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+225)?[0-9]{10}$/'],
            'preferred_payment_provider' => ['sometimes', 'nullable', 'string', 'in:wave,orange_money,mtn_money,moov_money'],
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
            'data' => new UserResource($user),
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
            'success' => true,
            'message' => 'Position mise à jour.',
            'position' => $data,
        ]);
    }

    public function setRole(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        // Un changement de rôle modifie le périmètre KYC et les capacités du
        // compte. Il ne peut donc jamais être effectué en libre-service.
        if ($actor->role !== 'admin' || Gate::forUser($actor)->denies('admin.users.manage')) {
            abort(403, 'Accès refusé.');
        }

        // Les comptes administrateurs et l'acteur lui-même sont gérés par le
        // workflow backoffice protégé afin d'éviter toute auto-rétrogradation.
        if ($user->role === 'admin' || $actor->is($user)) {
            abort(403, 'Le rôle de ce compte ne peut pas être modifié par cette route.');
        }

        $data = $request->validate([
            'role' => ['required', 'in:client,artisan,fournisseur'],
        ], [
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Rôle invalide.',
        ]);

        $previousRole = $user->role;
        DB::transaction(function () use ($user, $data): void {
            // Un dossier KYC validé pour un rôle ne vaut pas agrément pour un
            // autre rôle. Le nouveau périmètre doit être revu avant transaction.
            $user->update([
                'role' => $data['role'],
                'kyc_status' => 'en_attente',
            ]);

            if ($data['role'] === 'artisan') {
                ArtisanProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['intervient_la_nuit' => false]
                );
            }

            $user->tokens()->delete();
        });

        $this->audit->log(
            'user.role.updated',
            $user,
            [
                'before' => $previousRole,
                'after' => $data['role'],
                'kyc_reset' => true,
                'tokens_revoked' => true,
            ],
            actor: $actor,
        );

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade')),
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
            $updateData['cnmci_card_url'] = '/storage/'.$path;
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
            'data' => new UserResource($user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id !== $user->id) {
            abort(403, 'Accès refusé.');
        }

        try {
            // Le droit à l'effacement conserve la ligne utilisateur afin de ne
            // pas rompre les ledgers financiers et les pistes d'audit.
            $this->gdpr->anonymize($user, $request->user(), allowSelf: true);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre compte a été anonymisé avec succès.',
        ]);
    }
}
