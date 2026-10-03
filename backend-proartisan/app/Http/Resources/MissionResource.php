<?php

namespace App\Http\Resources;

use App\Models\Setting;
use App\States\Mission\MissionState;
use Carbon\CarbonInterface;
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
            // Présent uniquement quand la requête a chargé le compteur
            // (withCount) : évite toute requête supplémentaire par mission.
            'unreadMessagesCount' => $this->whenCounted('unread_messages_count'),
            'photos' => $this->photos_json ?? [],
            'status' => (string) $this->status,
            'statusLabel' => MissionState::labelFor((string) $this->status),
            'artisanResponseDeadline' => $this->artisanResponseDeadline()?->toIso8601String(),
            'finalApprovalDeadline' => $this->finalApprovalDeadline()?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'cancellationPenalty' => $this->cancellation_penalty,
            'cancellationRefund' => $this->cancellation_refund,
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
            'diagnosticMediaAnalysis' => $this->diagnostic_media_analysis,
            'diagnostic_media_analysis' => $this->diagnostic_media_analysis,
            'montantTotal' => $this->montant_total,
            'montantMateriaux' => $this->montant_materiaux,
            'montantMo' => $this->montant_mo,
            'ratioMateriaux' => $this->ratio_materiaux,
            'referentRequired' => $this->referent_required,
            'paymentStatus' => $this->mapPaymentStatus(),
            'location' => $this->shouldRevealClientDetails($request) ? $this->client_address : null,
            'address_id' => $this->shouldRevealClientDetails($request) ? $this->address_id : null,
            'addressId' => $this->shouldRevealClientDetails($request) ? $this->address_id : null,
            'clientAddress' => $this->shouldRevealClientDetails($request) ? $this->client_address : null,
            'clientCoordinates' => $this->shouldRevealClientDetails($request) && $this->client_latitude !== null && $this->client_longitude !== null ? [
                'lat' => $this->client_latitude,
                'lng' => $this->client_longitude,
            ] : null,
            'requestedSectorId' => $this->requested_sector_id,
            'requestedTradeId' => $this->requested_trade_id,
            'interventionTypeId' => $this->intervention_type_id,
            'interventionTypeName' => $this->relationLoaded('interventionType') ? $this->interventionType?->name : null,
            'client' => $this->when(
                $this->relationLoaded('client'),
                fn () => [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'phone' => $this->shouldRevealClientDetails($request) ? $this->client->phone : null,
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
            // Montants réels uniquement : les frais de plateforme, jamais calculés
            // ici, ne sont plus renvoyés à zéro (Règle d'or 29).
            'financials' => [
                'tokenAmount' => $this->montant_materiaux,
                'laborCost' => $this->montant_mo,
            ],
            'mention' => $this->hasFlag('pending_devis_count', fn () => $this->hasPendingDevis())
                ? 'En attente de validation du devis'
                : ($this->hasFlag('accepted_devis_count', fn () => $this->devisAccepte()->exists())
                    ? 'Devis accepté'
                    : null),
            'has_devis' => $this->hasFlag(
                'active_devis_count',
                fn () => $this->devis()->where('statut', '!=', 'refuse')->exists()
            ),
            'artisanRejected' => $this->artisan_rejected_at !== null && $this->artisan_id === null,
            'artisanRejectedAt' => $this->artisan_rejected_at?->toIso8601String(),
            'hasArtisan' => $this->artisan_id !== null,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Lit un drapeau « devis » depuis le compteur agrégé quand la requête l'a
     * chargé (scopeWithDevisFlags, utilisé par les listes), sinon retombe sur
     * la requête directe. Les listes évitent ainsi trois requêtes par mission,
     * sans que les endpoints unitaires aient à changer.
     */
    private function hasFlag(string $countAttribute, callable $fallback): bool
    {
        $count = $this->resource->getAttribute($countAttribute);

        return $count !== null ? $count > 0 : $fallback();
    }

    private function mapMissionStatusToGemini(mixed $status): string
    {
        $statusStr = (string) $status;

        return match ($statusStr) {
            'pending_artisan_acceptance' => 'pending_artisan_acceptance',
            'pending_funding' => 'sent',
            'funded_locked', 'financee' => 'funded',
            'in_progress', 'en_cours' => 'work_done',
            'pending_approval' => 'pending_approval',
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
            'funded_locked', 'in_progress', 'pending_approval', 'completed' => 'funded',
            'disputed' => $this->resource->isFunded() ? ($this->funds_frozen ? 'blocked' : 'funded') : 'pending',
            // « Remboursée » seulement si un séquestre avait été constitué.
            'cancelled' => (int) $this->montant_total > 0 ? 'refunded' : 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Échéance de réponse de l'artisan à la demande de devis.
     */
    private function artisanResponseDeadline(): ?CarbonInterface
    {
        if ((string) $this->status !== 'pending_artisan_acceptance' || ! $this->artisan_assigned_at) {
            return null;
        }

        return $this->artisan_assigned_at->copy()->addHours(max(1, (int) Setting::getValueByKey('mission_artisan_response_hours', 24)));
    }

    /**
     * Date de clôture automatique faute de validation finale du client.
     */
    private function finalApprovalDeadline(): ?CarbonInterface
    {
        if ((string) $this->status !== 'pending_approval' || ! $this->completion_requested_at) {
            return null;
        }

        return $this->completion_requested_at->copy()->addHours(max(1, (int) Setting::getValueByKey('mission_final_approval_hours', 72)));
    }

    /**
     * Détermine si les informations privées du client doivent être révélées.
     */
    private function shouldRevealClientDetails(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
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

        // L'artisan affecté a accès une fois la mission financée.
        if ($user->id === $this->artisan_id) {
            return $this->resource->isFunded();
        }

        return false;
    }
}
