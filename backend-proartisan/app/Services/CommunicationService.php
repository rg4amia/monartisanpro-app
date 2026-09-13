<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CommunicationService
{
    /**
     * Liste paginée des communications avec filtres optionnels.
     */
    public function list(?string $type, ?string $statut, int $perPage = 20): LengthAwarePaginator
    {
        $query = Communication::with('auteur:id,name,phone')
            ->orderByDesc('updated_at');

        if ($type) {
            $query->where('type', $type);
        }

        if ($statut) {
            $query->where('statut', $statut);
        }

        return $query->paginate($perPage);
    }

    /**
     * Récupère toutes les communications pour le backoffice (non paginé, limité).
     */
    public function listAll(int $limit = 100): Collection
    {
        return Communication::with('auteur:id,name,phone')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /** Dossier du disque public accueillant les enregistrements vocaux. */
    private const AUDIO_DIRECTORY = 'communications/audio';

    /**
     * Créer une communication en brouillon.
     */
    public function store(array $data, User $auteur, ?UploadedFile $mediaFile = null): Communication
    {
        $communication = Communication::create([
            'type'        => $data['type'],
            'titre'       => $data['titre'],
            'contenu'     => $data['contenu'],
            'cibles_json' => $data['cibles'],
            'statut'      => 'brouillon',
            'auteur_id'   => $auteur->id,
        ]);

        return $this->applyMedia($communication, $data, $mediaFile);
    }

    /**
     * Mettre à jour une communication (brouillon uniquement).
     */
    public function update(Communication $communication, array $data, ?UploadedFile $mediaFile = null): Communication
    {
        // Une publication clôturée est modifiable : c'est le seul moyen de
        // corriger puis rediffuser un contenu retiré, sans le ressaisir.
        // Une publication en cours reste figée — elle changerait sous les yeux
        // de ceux qui la consultent.
        if (! $communication->isEditable()) {
            throw new \LogicException(
                'Une communication en cours de diffusion ne peut pas être modifiée. Clôturez-la d\'abord.'
            );
        }

        $communication->update([
            'type'        => $data['type'] ?? $communication->type,
            'titre'       => $data['titre'] ?? $communication->titre,
            'contenu'     => $data['contenu'] ?? $communication->contenu,
            'cibles_json' => $data['cibles'] ?? $communication->cibles_json,
        ]);

        return $this->applyMedia($communication->fresh(), $data, $mediaFile);
    }

    /**
     * Attache le média au type courant et purge celui qui n'a plus lieu d'être.
     *
     * Changer le type d'une publication doit libérer l'ancien média : garder un
     * fichier audio orphelin sur le disque d'une publication devenue vidéo
     * consomme du quota sans que rien ne l'affiche ni ne le supprime jamais.
     */
    private function applyMedia(Communication $communication, array $data, ?UploadedFile $mediaFile): Communication
    {
        $attributes = [];

        if ($communication->isAudio()) {
            if ($mediaFile) {
                $this->deleteStoredAudio($communication);

                $attributes['media_path'] = $mediaFile->store(self::AUDIO_DIRECTORY, 'public');
                $attributes['media_mime'] = $mediaFile->getClientMimeType();
                $attributes['media_size'] = $mediaFile->getSize();
            }

            $attributes['media_external_url'] = null;
        } elseif ($communication->isVideo()) {
            $this->deleteStoredAudio($communication);

            $attributes['media_path'] = null;
            $attributes['media_mime'] = null;
            $attributes['media_size'] = null;
            $attributes['media_external_url'] = $data['media_external_url']
                ?? $communication->media_external_url;
        } else {
            $this->deleteStoredAudio($communication);

            $attributes = [
                'media_path'         => null,
                'media_external_url' => null,
                'media_mime'         => null,
                'media_size'         => null,
                'media_duration'     => null,
            ];
        }

        if ($communication->isAudio() || $communication->isVideo()) {
            $attributes['media_duration'] = $data['media_duration'] ?? $communication->media_duration;
        }

        $communication->update($attributes);

        return $communication->fresh();
    }

    /** Supprime le fichier audio du disque, s'il en existe un. */
    private function deleteStoredAudio(Communication $communication): void
    {
        if ($communication->media_path) {
            Storage::disk('public')->delete($communication->media_path);
        }
    }

    /**
     * Publier une communication (brouillon → publié).
     */
    public function publish(Communication $communication): Communication
    {
        if (! $communication->isPublishable()) {
            throw new \LogicException('Cette communication est déjà en cours de diffusion.');
        }

        // Une publication vocale ou vidéo sans média jouable n'afficherait
        // qu'une carte inerte à des milliers d'utilisateurs. Mieux vaut la
        // refuser ici que la diffuser.
        if (($communication->isAudio() || $communication->isVideo()) && ! $communication->hasPlayableMedia()) {
            throw new \LogicException(
                $communication->isAudio()
                    ? 'Cette publication vocale n\'a pas de fichier audio : ajoutez-le avant de publier.'
                    : 'Cette publication vidéo n\'a pas de lien : ajoutez-le avant de publier.'
            );
        }

        $communication->update([
            'statut'    => 'publie',
            'publie_at' => now(),
            // Rediffusion : sans cette remise à zéro, la ligne resterait
            // marquée comme clôturée tout en étant publiée.
            'cloture_at' => null,
        ]);

        return $communication->fresh();
    }

    /**
     * Clôturer une communication (publié → clôturé).
     */
    public function cloturer(Communication $communication): Communication
    {
        if (! $communication->isPublie()) {
            throw new \LogicException('Seule une communication publiée peut être clôturée.');
        }

        $communication->update([
            'statut'     => 'cloture',
            'cloture_at' => now(),
        ]);

        return $communication->fresh();
    }

    /**
     * Supprimer une communication (brouillon uniquement).
     */
    public function destroy(Communication $communication): void
    {
        // Comme la modification : brouillon ou clôturée, jamais en diffusion.
        if (! $communication->isEditable()) {
            throw new \LogicException(
                'Une communication en cours de diffusion ne peut pas être supprimée. Clôturez-la d\'abord.'
            );
        }

        // Le fichier part avec la ligne : sinon le disque accumule des
        // enregistrements que plus aucune publication ne référence.
        $this->deleteStoredAudio($communication);

        $communication->delete();
    }

    /**
     * Récupérer les communications actives pour un rôle donné.
     * Retourne séparément les annonces et les astuces "Le saviez-vous".
     */
    public function getActiveForRole(string $role): array
    {
        $communications = Communication::publie()
            ->forRole($role)
            ->orderByDesc('publie_at')
            ->get();

        // Un média devenu injouable — fichier effacé du disque, lien vidé —
        // n'est pas diffusé : l'application afficherait une carte morte.
        $playable = fn (Communication $c) => $c->hasPlayableMedia();

        return [
            'annonces'       => $communications->where('type', 'annonce')->values(),
            'le_saviez_vous' => $communications->where('type', 'le_saviez_vous')->values(),
            'audio'          => $communications->where('type', Communication::TYPE_AUDIO)->filter($playable)->values(),
            'video'          => $communications->where('type', Communication::TYPE_VIDEO)->filter($playable)->values(),
        ];
    }
}
