<?php

namespace App\Services;

use App\Exceptions\ParrainageClientException;
use App\Models\CampagneParrainage;
use App\Models\ParrainageClient;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parrainage client → client. Distinct du parrainage artisan existant
 * (Parrainage / ParrainageController, hors périmètre) : un client parraine
 * un autre client et reçoit, à la première mission financée de son filleul,
 * un code promo auto-généré dont le taux est piloté par une campagne de
 * parrainage active côté backoffice.
 */
class ParrainageClientService
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Enregistre un parrainage d'un client (filleul) par un autre client
     * (parrain), ou crée une invitation en attente si le filleul n'a pas
     * encore de compte (Règle d'or 35 : SMS d'invitation, liaison
     * automatique à l'inscription via `linkPending()`).
     *
     * @throws ParrainageClientException
     */
    public function parrainer(User $parrain, string $filleulPhone, string $filleulNom): ParrainageClient
    {
        // RÈGLE : Seul un client peut être parrain
        if ($parrain->role !== 'client') {
            throw new ParrainageClientException(
                'Seuls les clients peuvent parrainer d\'autres clients.',
                403
            );
        }

        // RÈGLE : Un numéro ne peut être parrainé/invité qu'une seule fois
        // (couvre à la fois « déjà lié à un parrain » et « déjà invité en
        // attente »). Vérifié avant la recherche du filleul pour éviter
        // qu'un même numéro non inscrit reçoive deux invitations.
        if (ParrainageClient::where('filleul_phone', $filleulPhone)->exists()) {
            throw new ParrainageClientException(
                'Ce client a déjà un parrain.',
                422
            );
        }

        $filleul = User::where('phone', $filleulPhone)->first();

        if (! $filleul) {
            $parrainage = ParrainageClient::create([
                'parrain_id' => $parrain->id,
                'filleul_phone' => $filleulPhone,
                'filleul_nom' => $filleulNom,
                'statut' => 'en_attente_inscription',
            ]);

            app(SmsService::class)->send(
                $filleulPhone,
                "{$parrain->name} vous invite à rejoindre ProsArtisan ! Inscrivez-vous avec ce numéro pour bénéficier d'une réduction sur votre première mission."
            );

            return $parrainage;
        }

        // RÈGLE : Le filleul doit être un client
        if ($filleul->role !== 'client') {
            throw new ParrainageClientException(
                'Le filleul doit avoir le rôle client.',
                422
            );
        }

        // RÈGLE : Ne pas se parrainer soi-même
        if ($filleul->id === $parrain->id) {
            throw new ParrainageClientException(
                'Vous ne pouvez pas vous parrainer vous-même.',
                422
            );
        }

        return ParrainageClient::create([
            'parrain_id' => $parrain->id,
            'filleul_id' => $filleul->id,
            'filleul_phone' => $filleul->phone,
            'statut' => 'en_attente',
        ]);
    }

    /**
     * Lie automatiquement les invitations en attente correspondant au
     * téléphone de l'utilisateur qui vient de s'inscrire, si son rôle
     * définitif est client. No-op silencieux sinon.
     */
    public function linkPending(User $user): void
    {
        if ($user->role !== 'client') {
            return;
        }

        ParrainageClient::where('filleul_phone', $user->phone)
            ->where('statut', 'en_attente_inscription')
            ->update(['filleul_id' => $user->id, 'statut' => 'en_attente']);
    }

    /**
     * Récompense le parrain dès que son filleul finance sa première mission
     * (mission passée à `funded_locked`), si une campagne de parrainage est
     * active. Sans campagne active, le parrainage reste `en_attente` — ce
     * n'est pas une erreur.
     */
    public function recompenserSiEligible(User $filleul): void
    {
        $parrainage = ParrainageClient::where('filleul_id', $filleul->id)
            ->where('statut', 'en_attente')
            ->first();

        if (! $parrainage) {
            return;
        }

        $campagne = CampagneParrainage::where('is_active', true)
            ->get()
            ->first(fn (CampagneParrainage $c) => $c->estActive());

        if (! $campagne) {
            return;
        }

        DB::transaction(function () use ($parrainage, $campagne) {
            $code = $this->generateUniqueCode();

            $promoCode = PromoCode::create([
                'code' => $code,
                'description' => 'Récompense de parrainage client',
                'discount_type' => $campagne->discount_type,
                'discount_value' => $campagne->discount_value,
                'min_order_amount' => $campagne->min_montant,
                'max_discount_amount' => $campagne->max_discount_amount,
                'usage_limit' => 1,
                'used_count' => 0,
                'is_active' => true,
                'owner_user_id' => $parrainage->parrain_id,
                'starts_at' => now(),
                'expires_at' => $campagne->expires_at ?? now()->addDays(60),
            ]);

            $parrainage->update([
                'statut' => 'recompense',
                'campagne_id' => $campagne->id,
                'promo_code_id' => $promoCode->id,
                'recompense_at' => now(),
            ]);

            $this->notificationService->send(
                $parrainage->parrain,
                'referral_reward',
                'Récompense de parrainage',
                "Félicitations ! Votre filleul a financé sa première mission. Voici votre code promo : {$promoCode->code}",
                ['promo_code' => $promoCode->code]
            );
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'PARR-'.strtoupper(Str::random(6));
        } while (PromoCode::where('code', $code)->exists());

        return $code;
    }
}
