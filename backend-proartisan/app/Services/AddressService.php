<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function listForUser(User $user): Collection
    {
        return Address::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Crée une adresse pour l'utilisateur. La toute première adresse d'un
     * client devient automatiquement son adresse par défaut, sans quoi le
     * checkout n'aurait aucune adresse à proposer.
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirstAddress = ! Address::where('user_id', $user->id)->exists();
            $makeDefault = $isFirstAddress || (bool) ($data['is_default'] ?? false);

            if ($makeDefault) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = Address::create([
                'user_id' => $user->id,
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'region' => $data['region'] ?? null,
                'country' => $data['country'] ?? "Côte d'Ivoire",
                'is_default' => $makeDefault,
            ]);

            if (isset($data['latitude'], $data['longitude'])) {
                $address->setPosition((float) $data['latitude'], (float) $data['longitude']);
            }

            return $address->fresh();
        });
    }

    /**
     * Met à jour les champs d'une adresse existante. Le statut par défaut ne
     * se change que via [setDefault], jamais implicitement ici, pour éviter
     * qu'une mise à jour anodine ne laisse le carnet sans adresse par défaut.
     */
    public function update(Address $address, array $data): Address
    {
        $address->fill(array_filter([
            'label' => $data['label'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'address_line' => $data['address_line'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'country' => $data['country'] ?? null,
        ], fn ($value) => $value !== null));
        $address->save();

        if (isset($data['latitude'], $data['longitude'])) {
            $address->setPosition((float) $data['latitude'], (float) $data['longitude']);
        }

        return $address->fresh();
    }

    /**
     * Supprime une adresse. Si elle était l'adresse par défaut, la plus
     * récente des adresses restantes est promue par défaut — le carnet ne
     * doit jamais se retrouver sans adresse par défaut tant qu'il en compte
     * au moins une, sous peine de reproduire le bug initial du checkout.
     */
    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;
            $address->delete();

            if ($wasDefault) {
                Address::where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }

    public function setDefault(Address $address): Address
    {
        return DB::transaction(function () use ($address) {
            Address::where('user_id', $address->user_id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}
