<?php

namespace App\Services;

use App\Models\FournisseurAgree;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fiche boutique d'un fournisseur (Chantier 27, lot E).
 *
 * La fiche n'est jamais créée avec des coordonnées inventées : elle naît
 * quand le fournisseur partage sa position réelle depuis l'application, au
 * statut « en attente », puis l'administrateur l'agrée. Auparavant chaque
 * compte fournisseur recevait d'office une « Quincaillerie de … » placée à un
 * point fixe d'Abidjan.
 */
class SupplierShopService
{
    /**
     * Enregistre la position réelle du fournisseur sur sa fiche, en la créant
     * si elle n'existe pas encore.
     */
    public function recordPosition(User $supplier, float $lat, float $lng): ?FournisseurAgree
    {
        if ($supplier->role !== 'fournisseur') {
            return null;
        }

        $shop = $supplier->fournisseurAgree;

        if ($shop) {
            $shop->setPosition($lat, $lng);

            return $shop;
        }

        // Le nom du compte tient lieu de nom de boutique jusqu'à sa correction
        // par l'administrateur, à l'agrément.
        $name = mb_substr((string) ($supplier->name ?: $supplier->phone), 0, 150);

        if (config('database.default') === 'sqlite') {
            DB::table('fournisseurs_agrees')->insert([
                'user_id' => $supplier->id,
                'nom_boutique' => $name,
                'statut' => 'en_attente',
                'position' => "{$lat},{$lng}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::statement(
                'INSERT INTO fournisseurs_agrees (user_id, nom_boutique, statut, position, created_at, updated_at) VALUES (?, ?, ?, POINT(?, ?), ?, ?)',
                [$supplier->id, $name, 'en_attente', $lng, $lat, now(), now()],
            );
        }

        return $supplier->fresh()->fournisseurAgree;
    }
}
