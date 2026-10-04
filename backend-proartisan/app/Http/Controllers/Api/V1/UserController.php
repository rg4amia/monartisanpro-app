<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ArtisanProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminGdprService;
use App\Services\Admin\AdminUserService;
use App\Services\PaymentPhoneService;
use App\Services\SupplierShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private AdminGdprService $gdpr,
        private AdminActivityLogger $audit,
        private AdminUserService $accounts,
        private SupplierShopService $shops,
        private PaymentPhoneService $paymentPhones,
    ) {}

    /**
     * Le titulaire agit sur son propre compte ; un administrateur n'agit sur
     * celui d'un tiers qu'avec la capacité de gestion des utilisateurs
     * (Règle d'or 36). Retourne vrai quand l'appelant est le titulaire.
     */
    private function actsOnOwnAccount(Request $request, User $user): bool
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return true;
        }

        if ($actor->role !== 'admin' || Gate::forUser($actor)->denies('admin.users.manage')) {
            abort(403, 'Accès refusé.');
        }

        return false;
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $own = $this->actsOnOwnAccount($request, $user);

        // Le numéro de paiement reçoit les versements : seul le titulaire le
        // modifie par cette route.
        if (! $own && ($request->has('payment_phone') || $request->has('preferred_payment_provider'))) {
            throw ValidationException::withMessages([
                'payment_phone' => ['Le moyen de paiement d\'un utilisateur ne se modifie pas depuis cette route.'],
            ]);
        }

        $before = ['name' => $user->name];

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
            'payment_phone_code' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        // Le numéro de paiement suit son propre circuit : code de
        // confirmation, notification du titulaire, suspension des retraits.
        $paymentCode = $data['payment_phone_code'] ?? null;
        unset($data['payment_phone_code']);
        if (array_key_exists('payment_phone', $data)) {
            if (filled($data['payment_phone'])) {
                $this->paymentPhones->update($user, $data['payment_phone'], $data['preferred_payment_provider'] ?? null, $paymentCode);
                unset($data['payment_phone'], $data['preferred_payment_provider']);
            } elseif ($this->paymentPhones->isProtected($user)) {
                // Effacer le numéro renverrait les versements vers le numéro du compte : sans effet utile.
                unset($data['payment_phone']);
            }
        }

        $submitted = array_keys($data);

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

        if (! $own) {
            $this->audit->log('user.profile.updated_by_admin', $user, [
                'champs' => array_values(array_diff($submitted, ['fcm_token'])),
                'before' => $before,
                'after' => ['name' => $user->name],
            ], actor: $request->user());
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Envoie au numéro du compte le code qui confirme un changement de numéro
     * de paiement.
     */
    public function requestPaymentPhoneCode(Request $request): JsonResponse
    {
        $this->paymentPhones->sendConfirmationCode($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Code de confirmation envoyé par SMS au numéro de votre compte.',
        ]);
    }

    public function updateLocation(Request $request, User $user): JsonResponse
    {
        $own = $this->actsOnOwnAccount($request, $user);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'lat.required' => 'La latitude est obligatoire.',
            'lng.required' => 'La longitude est obligatoire.',
        ]);

        $user->setPosition((float) $data['lat'], (float) $data['lng']);

        // La fiche boutique naît de la position réelle du fournisseur, jamais
        // de coordonnées par défaut.
        $this->shops->recordPosition($user, (float) $data['lat'], (float) $data['lng']);

        if (! $own) {
            $this->audit->log('user.location.updated_by_admin', $user, [], actor: $request->user());
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

        // Même circuit que le backoffice : compte libre de tout engagement,
        // KYC remis en attente, sessions fermées, ligne d'audit.
        try {
            $this->accounts->changeRole($user, $data['role'], $actor);
        } catch (\LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }

    public function updateCnmci(Request $request, User $user): JsonResponse
    {
        $own = $this->actsOnOwnAccount($request, $user);

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les artisans peuvent s\'affilier à la CNMCI.',
            ], 422);
        }

        $data = $request->validate([
            'cnmci_number' => ['nullable', 'string', 'max:100'],
            'cnmci_card' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'cnmci_card.image' => 'La carte CNMCI doit être une image.',
            'cnmci_card.mimes' => 'Formats acceptés pour la carte CNMCI : JPEG, PNG, WEBP.',
            'cnmci_card.max' => 'La carte CNMCI ne doit pas dépasser 4 Mo.',
        ]);

        $updateData = [];

        if (array_key_exists('cnmci_number', $data)) {
            $updateData['cnmci_number'] = $data['cnmci_number'];
        }

        if ($request->hasFile('cnmci_card')) {
            // Disque privé : une carte professionnelle nominative ne se sert
            // jamais par une adresse publique permanente (Règle d'or 40).
            $previousPath = $user->cnmciCardPath();
            $updateData['cnmci_card_url'] = $request->file('cnmci_card')->store('cnmci', 'local');
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

        if (isset($updateData['cnmci_card_url']) && ! empty($previousPath) && ! User::isLegacyPublicPath($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        if (! $own) {
            $this->audit->log('user.cnmci.updated_by_admin', $user, [
                'carte_remplacee' => isset($updateData['cnmci_card_url']),
            ], actor: $request->user());
        }

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
