<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Créer une communication en brouillon.
     */
    public function store(array $data, User $auteur): Communication
    {
        return Communication::create([
            'type'        => $data['type'],
            'titre'       => $data['titre'],
            'contenu'     => $data['contenu'],
            'cibles_json' => $data['cibles'],
            'statut'      => 'brouillon',
            'auteur_id'   => $auteur->id,
        ]);
    }

    /**
     * Mettre à jour une communication (brouillon uniquement).
     */
    public function update(Communication $communication, array $data): Communication
    {
        if (! $communication->isBrouillon()) {
            throw new \LogicException('Seule une communication en brouillon peut être modifiée.');
        }

        $communication->update([
            'type'        => $data['type'] ?? $communication->type,
            'titre'       => $data['titre'] ?? $communication->titre,
            'contenu'     => $data['contenu'] ?? $communication->contenu,
            'cibles_json' => $data['cibles'] ?? $communication->cibles_json,
        ]);

        return $communication->fresh();
    }

    /**
     * Publier une communication (brouillon → publié).
     */
    public function publish(Communication $communication): Communication
    {
        if (! $communication->isBrouillon()) {
            throw new \LogicException('Seule une communication en brouillon peut être publiée.');
        }

        $communication->update([
            'statut'    => 'publie',
            'publie_at' => now(),
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
        if (! $communication->isBrouillon()) {
            throw new \LogicException('Seule une communication en brouillon peut être supprimée.');
        }

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

        return [
            'annonces'       => $communications->where('type', 'annonce')->values(),
            'le_saviez_vous' => $communications->where('type', 'le_saviez_vous')->values(),
        ];
    }
}
