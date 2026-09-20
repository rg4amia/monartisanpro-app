<?php

namespace App\Services;

use App\Exceptions\ParrainageException;
use App\Models\Parrainage;
use App\Models\User;

/**
 * Parrainage artisan → apprenti (Maître Artisan parrainant un futur
 * artisan). Distinct du parrainage client → client (ParrainageClientService,
 * hors périmètre de cette classe).
 *
 * Permet d'inviter par téléphone un futur artisan qui n'a pas encore de
 * compte : une ligne `en_attente_inscription` est créée et un SMS
 * d'invitation est envoyé ; la liaison se fait automatiquement dès que ce
 * numéro s'inscrit avec le rôle artisan (voir `linkPending()`, appelée
 * depuis `AuthService::register()`).
 */
class ParrainageService
{
    /**
     * Enregistre un parrainage d'un apprenti (filleul) par un Maître Artisan
     * (parrain), ou crée une invitation en attente si le filleul n'a pas
     * encore de compte.
     *
     * @throws ParrainageException
     */
    public function parrainer(User $parrain, string $filleulPhone, string $filleulNom): Parrainage
    {
        // RÈGLE : Seul un artisan peut être parrain
        if (! $parrain->isArtisan()) {
            throw new ParrainageException(
                'Action non autorisée. Seuls les artisans peuvent être parrains.',
                403
            );
        }

        // RÈGLE : Score ProsArtisan > 800 requis
        if ($parrain->score_prosartisan <= 800) {
            throw new ParrainageException(
                'Score ProsArtisan insuffisant pour parrainer (minimum 800).',
                422
            );
        }

        // RÈGLE : Un numéro ne peut être parrainé/invité qu'une seule fois
        // (couvre à la fois « déjà lié à un parrain » et « déjà invité en
        // attente »). Vérifié avant la recherche du filleul pour éviter
        // qu'un même numéro non inscrit reçoive deux invitations de deux
        // parrains différents.
        if (Parrainage::where('filleul_phone', $filleulPhone)->exists()) {
            throw new ParrainageException(
                'Ce numéro a déjà été parrainé ou invité.',
                422
            );
        }

        $filleul = User::where('phone', $filleulPhone)->first();

        if (! $filleul) {
            $parrainage = Parrainage::create([
                'parrain_id' => $parrain->id,
                'filleul_phone' => $filleulPhone,
                'filleul_nom' => $filleulNom,
                'statut' => 'en_attente_inscription',
                'score_caution' => 0,
            ]);

            app(SmsService::class)->send(
                $filleulPhone,
                "{$parrain->name} vous invite à rejoindre ProsArtisan en tant qu'artisan. Inscrivez-vous avec ce numéro pour profiter du parrainage !"
            );

            return $parrainage;
        }

        // RÈGLE : Le filleul doit être un artisan
        if (! $filleul->isArtisan()) {
            throw new ParrainageException(
                'Le filleul coopté doit avoir le rôle d\'artisan.',
                422
            );
        }

        // RÈGLE : Ne pas se parrainer soi-même
        if ($filleul->id === $parrain->id) {
            throw new ParrainageException(
                'Vous ne pouvez pas vous parrainer vous-même.',
                422
            );
        }

        $parrainage = Parrainage::create([
            'parrain_id' => $parrain->id,
            'filleul_id' => $filleul->id,
            'filleul_phone' => $filleul->phone,
            'statut' => 'actif',
            'score_caution' => 0,
        ]);

        return $parrainage->load('filleul');
    }

    /**
     * Lie automatiquement les invitations en attente correspondant au
     * téléphone de l'utilisateur qui vient de s'inscrire, si son rôle
     * définitif est artisan. No-op silencieux sinon (aucune invitation en
     * attente, ou mauvais rôle) : aucun impact sur le flux d'inscription
     * normal.
     */
    public function linkPending(User $user): void
    {
        if ($user->role !== 'artisan') {
            return;
        }

        Parrainage::where('filleul_phone', $user->phone)
            ->where('statut', 'en_attente_inscription')
            ->update(['filleul_id' => $user->id, 'statut' => 'actif']);
    }
}
