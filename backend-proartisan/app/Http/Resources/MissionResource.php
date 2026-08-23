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
            'status' => (string) $this->status,
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
            'location' => $this->shouldRevealClientDetails($request) ? $this->client_address : null,
            'clientAddress' => $this->shouldRevealClientDetails($request) ? $this->client_address : null,
            'clientCoordinates' => $this->shouldRevealClientDetails($request) && $this->client_latitude !== null && $this->client_longitude !== null ? [
                'lat' => $this->client_latitude,
                'lng' => $this->client_longitude,
            ] : null,
            'requestedSectorId' => $this->requested_sector_id,
            'requestedTradeId' => $this->requested_trade_id,
            'client' => $this->when(
                $this->relationLoaded('client'),
                fn () => [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'phone' => $this->shouldRevealClientDetails($request) ? $this->client->phone : null
                ]
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
            'mention' => $this->hasPendingDevis() 
                ? "En attente de validation du devis" 
                : ($this->devisAccepte()->exists() ? "Devis accepté" : null),
            'has_devis' => $this->devis()->where('statut', '!=', 'refuse')->exists(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function mapMissionStatusToGemini(mixed $status): string
    {
        $statusStr = (string) $status;
        return match ($statusStr) {
            'pending_artisan_acceptance' => 'pending_artisan_acceptance',
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
        $statusStr = (string) $this->status;
        return match ($statusStr) {
            'funded_locked', 'financee', 'in_progress', 'en_cours', 'completed', 'terminee' => 'funded',
            'disputed', 'litige' => $this->funds_frozen ? 'blocked' : 'funded',
            'cancelled', 'annulee' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Détermine si les informations privées du client doivent être révélées.
     */
    private function shouldRevealClientDetails(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        // L'admin a accès à tout
        if ($user->role === 'admin') {
            return true;
        }

        // Le client a accès à sa propre mission
        if ($user->id === $this->client_id) {
            return true;
        }

        // L'artisan affecté a accès si la mission est financée/payée
        if ($user->id === $this->artisan_id) {
            $statusStr = (string) $this->status;
            $paidStatuses = [
                'funded_locked', 'financee',
                'in_progress', 'en_cours',
                'pending_approval',
                'completed', 'terminee',
                'disputed', 'litige'
            ];
            return in_array($statusStr, $paidStatuses, true);
        }

        return false;
    }
}
