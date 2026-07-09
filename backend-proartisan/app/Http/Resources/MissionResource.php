<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clientName = $this->relationLoaded('client') ? $this->client?->name : null;
        $artisanName = $this->relationLoaded('artisan') ? $this->artisan?->name : null;
        $category = $this->gemini_category
            ?? ($this->relationLoaded('requestedTrade') ? $this->requestedTrade?->name : null)
            ?? ($this->relationLoaded('requestedSector') ? $this->requestedSector?->name : null);

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'clientId' => $this->client_id,
            'artisan_id' => $this->artisan_id,
            'artisanId' => $this->artisan_id,
            'description' => $this->description,
            'problem' => $this->description,
            'photos' => $this->photos_json ?? [],
            'status' => $this->status,
            'statusGemini' => $this->mapMissionStatusToGemini($this->status),
            'geminiCategory' => $this->gemini_category,
            'geminiUrgency' => $this->gemini_urgency,
            'category' => $category,
            'artisanCategory' => $category,
            'urgency' => $this->gemini_urgency,
            'geminiEstimation' => $this->gemini_estimation_min && $this->gemini_estimation_max ? [
                'min' => $this->gemini_estimation_min,
                'max' => $this->gemini_estimation_max,
            ] : null,
            'montantTotal' => $this->montant_total,
            'montantMateriaux' => $this->montant_materiaux,
            'montantMo' => $this->montant_mo,
            'ratioMateriaux' => $this->ratio_materiaux,
            'referentRequired' => $this->referent_required,
            'paymentStatus' => $this->mapPaymentStatus(),
            'location' => $this->client_address,
            'clientAddress' => $this->client_address,
            'clientCoordinates' => $this->client_latitude !== null && $this->client_longitude !== null ? [
                'lat' => $this->client_latitude,
                'lng' => $this->client_longitude,
            ] : null,
            'requestedSectorId' => $this->requested_sector_id,
            'requestedTradeId' => $this->requested_trade_id,
            'client' => $this->when(
                $this->relationLoaded('client'),
                fn () => ['id' => $this->client->id, 'name' => $this->client->name, 'phone' => $this->client->phone]
            ),
            'artisan' => $this->when(
                $this->relationLoaded('artisan') && $this->artisan,
                fn () => ['id' => $this->artisan->id, 'name' => $this->artisan->name]
            ),
            'clientName' => $clientName,
            'artisanName' => $artisanName,
            'jalons' => $this->when(
                $this->relationLoaded('jalons'),
                fn () => JalonResource::collection($this->jalons)
            ),
            'milestones' => $this->when(
                $this->relationLoaded('jalons'),
                fn () => $this->jalons->map(fn ($jalon) => [
                    'id' => $jalon->id,
                    'status' => $this->mapJalonStatusToGemini($jalon->statut),
                ])->values()
            ),
            'wallets' => [
                'materiaux' => $this->montant_materiaux,
                'mo' => $this->montant_mo,
            ],
            'financials' => [
                'tokenCode' => null,
                'tokenAmount' => $this->montant_materiaux,
                'laborCost' => $this->montant_mo,
                'platformFeesBreakdown' => [
                    'labor' => 0,
                    'material' => 0,
                    'delivery' => 0,
                ],
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function mapMissionStatusToGemini(string $status): string
    {
        return match ($status) {
            'pending_funding' => 'sent',
            'funded_locked', 'financee' => 'funded',
            'in_progress', 'en_cours' => 'work_done',
            'completed', 'terminee' => 'completed',
            'disputed', 'litige' => 'disputed',
            'cancelled', 'annulee' => 'cancelled',
            default => 'sent',
        };
    }

    private function mapJalonStatusToGemini(string $status): string
    {
        return match ($status) {
            'valide' => 'validated',
            'paye' => 'completed',
            default => 'pending',
        };
    }

    private function mapPaymentStatus(): string
    {
        return match ($this->status) {
            'funded_locked', 'financee', 'in_progress', 'en_cours', 'completed', 'terminee' => 'funded',
            'disputed', 'litige' => $this->funds_frozen ? 'blocked' : 'funded',
            'cancelled', 'annulee' => 'refunded',
            default => 'pending',
        };
    }
}
