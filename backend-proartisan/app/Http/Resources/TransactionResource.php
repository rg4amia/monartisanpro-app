<?php

namespace App\Http\Resources;

use App\Services\TransactionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mission = $this->relationLoaded('mission') ? $this->mission : null;

        // Sens et libellé calculés pour l'utilisateur qui consulte.
        $view = app(TransactionPresenter::class)->describe($this->resource, $request->user());

        return [
            'direction' => $view['direction'],
            'libelle' => $view['libelle'],
            'statutLibelle' => $view['statut_libelle'],
            'montantSigne' => $view['montant_signe'],
            'orderId' => $this->metadata['order_id'] ?? null,
            'payoutId' => $this->metadata['payout_id'] ?? null,
            'id' => $this->id,
            'type' => $this->type,
            'montant' => $this->montant,
            'walletSource' => $this->wallet_source,
            'walletDest' => $this->wallet_dest,
            'provider' => is_object($this->provider) ? $this->provider->value : (string) $this->provider,
            'statut' => is_object($this->statut) ? $this->statut->value : (string) $this->statut,
            'referenceExterne' => $this->reference_externe,
            'missionId' => $this->mission_id,
            'missionDescription' => $mission?->description,
            'clientName' => $mission?->client?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
