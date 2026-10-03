<?php

namespace App\Services;

use App\Exceptions\MissionActionException;
use App\Models\Address;
use App\Models\InterventionType;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\States\Mission\CancelledState;
use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingArtisanAcceptanceState;
use App\States\Mission\PendingFundingState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionService
{
    /** États que l'administrateur peut forcer, par leur clé technique. */
    public const FORCEABLE_STATES = [
        'draft' => DraftState::class,
        'pending_artisan_acceptance' => PendingArtisanAcceptanceState::class,
        'pending_funding' => PendingFundingState::class,
        'funded_locked' => FundedLockedState::class,
        'in_progress' => InProgressState::class,
        'pending_approval' => PendingApprovalState::class,
        'completed' => CompletedState::class,
        'disputed' => DisputedState::class,
        'cancelled' => CancelledState::class,
    ];

    public function __construct(
        private GeminiService $geminiService,
        private NotificationService $notificationService,
        private MissionLifecycleService $lifecycle,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * Crée une mission et appelle Gemini pour l'estimation.
     */
    public function create(User $client, array $data): Mission
    {
        // 0. Sécurité anti-doublon (évite la création multiple en cas de double-clic ou soumission en rafale)
        $duplicate = Mission::where('client_id', $client->id)
            ->where('artisan_id', $data['artisan_id'] ?? null)
            ->where('description', $data['description'])
            ->where('created_at', '>=', now()->subSeconds(15))
            ->first();

        if ($duplicate) {
            return $duplicate;
        }

        $hasArtisan = ! empty($data['artisan_id']);
        $initialState = $hasArtisan
            ? PendingArtisanAcceptanceState::class
            : DraftState::class;

        $locationAddress = $data['location_address'] ?? $data['location'] ?? null;
        $clientLat = $data['lat'] ?? null;
        $clientLng = $data['lng'] ?? null;
        $addressId = null;

        if (! empty($data['address_id'])) {
            $address = Address::find($data['address_id']);
            if (! $address || $address->user_id !== $client->id) {
                abort(403, 'Cette adresse ne vous appartient pas.');
            }

            $addressId = $address->id;
            if (empty($locationAddress)) {
                $parts = array_filter([$address->address_line, $address->city, $address->region]);
                $locationAddress = ! empty($parts) ? implode(', ', $parts) : ($address->label ?? 'Adresse enregistrée');
            }

            if ($clientLat === null && $clientLng === null) {
                $coords = $address->getPositionCoords();
                if ($coords) {
                    $clientLat = $coords['lat'];
                    $clientLng = $coords['lng'];
                }
            }
        }

        // RÈGLE : le client sélectionne le type d'intervention souhaité à la
        // demande de devis. Les anciennes versions mobiles ne l'envoient pas
        // encore : on retombe alors sur le premier type disponible.
        $interventionTypeId = $data['intervention_type_id']
            ?? InterventionType::orderBy('id')->value('id');

        $mission = Mission::create([
            'client_id' => $client->id,
            'address_id' => $addressId,
            'artisan_id' => $data['artisan_id'] ?? null,
            'artisan_assigned_at' => $hasArtisan ? now() : null,
            'requested_sector_id' => $data['sector_id'] ?? null,
            'requested_trade_id' => $data['trade_id'] ?? null,
            'intervention_type_id' => $interventionTypeId,
            'description' => $data['description'],
            'photos_json' => $data['photos'] ?? null,
            'status' => $initialState,
            'client_latitude' => $clientLat,
            'client_longitude' => $clientLng,
            'client_address' => $locationAddress,
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.0000,
            'diagnostic_media_analysis' => $data['diagnostic_media_analysis'] ?? null,
        ]);

        // Enrichissement Gemini. Sans estimation exploitable, les champs
        // restent vides : une fourchette inventée serait prise pour une
        // estimation réelle (Règle d'or 29).
        try {
            $estimate = $this->geminiService->analyzeMission($data['description'], [
                'category' => $data['category'] ?? null,
                'location_address' => $data['location_address'] ?? null,
            ]);

            $urgency = match (strtolower((string) ($estimate['urgency'] ?? 'moyen'))) {
                'faible', 'low', 'normale', 'normal' => 'faible',
                'urgent', 'haute', 'high', 'elevee', 'élevée' => 'urgent',
                default => 'moyen',
            };

            $priceMin = (int) ($estimate['price_min'] ?? 0);
            $priceMax = (int) ($estimate['price_max'] ?? 0);

            $mission->update([
                'gemini_category' => $estimate['category'] ?? null,
                'gemini_urgency' => $urgency,
                'gemini_estimation_min' => $priceMin > 0 && $priceMax > 0 ? $priceMin : null,
                'gemini_estimation_max' => $priceMin > 0 && $priceMax > 0 ? $priceMax : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Échec enrichissement Gemini: '.$e->getMessage());
        }

        if ($hasArtisan) {
            try {
                $artisan = User::find($data['artisan_id']);
                if ($artisan) {
                    $clientName = $client->name ?? 'Client';
                    $this->notificationService->notify(
                        $artisan,
                        'mission.demande_devis.artisan',
                        ['client' => $clientName],
                        ['mission_id' => $mission->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Échec envoi notification artisan: '.$e->getMessage());
            }
        }

        return $mission->fresh();
    }

    /**
     * Analyse le besoin du client via Gemini API.
     */
    public function estimate(array $data): array
    {
        return $this->geminiService->analyzeMission($data['description'] ?? '', [
            'category' => $data['category'] ?? null,
            'location_address' => $data['location_address'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Demande de devis : assignation, acceptation, refus
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Le client assigne ou réassigne un artisan à sa demande de devis.
     */
    public function assignArtisan(Mission $mission, User $client, int $artisanId): Mission
    {
        if ((int) $mission->client_id !== (int) $client->id) {
            throw new MissionActionException('Accès refusé.', 403);
        }

        if (! ($mission->status instanceof DraftState || $mission->status instanceof PendingArtisanAcceptanceState)) {
            throw new MissionActionException('Impossible de modifier l\'artisan pour une mission déjà financée ou en cours.', 400);
        }

        if ($mission->hasPendingDevis()) {
            throw new MissionActionException('Cette mission a un devis en cours d\'examen et ne peut pas être réassignée.');
        }

        $artisan = User::find($artisanId);

        if (! $artisan || $artisan->role !== 'artisan') {
            throw new MissionActionException('L\'utilisateur sélectionné n\'est pas un artisan.');
        }

        if (! $artisan->isKycActif() || ! $artisan->isAccountActive()) {
            throw new MissionActionException('Le profil de cet artisan n\'est pas encore validé.');
        }

        $previous = (int) $mission->artisan_id !== (int) $artisan->id ? $mission->artisan : null;

        DB::transaction(function () use ($mission, $artisan, $client): void {
            $mission->update([
                'artisan_id' => $artisan->id,
                'artisan_rejected_at' => null,
                'artisan_assigned_at' => now(),
                'artisan_reminded_at' => null,
            ]);

            $this->lifecycle->transition(
                $mission,
                PendingArtisanAcceptanceState::class,
                $client,
                'Demande de devis envoyée',
                ['artisan_id' => $artisan->id],
            );
        });

        // L'artisan remplacé apprend que la demande ne lui est plus destinée.
        if ($previous) {
            $this->notificationService->notify($previous, 'mission.demande_retiree.artisan', [], ['mission_id' => $mission->id]);
        }

        $this->notificationService->notify(
            $artisan,
            'mission.demande_devis.artisan',
            ['client' => $client->name ?? 'Client'],
            ['mission_id' => $mission->id]
        );

        return $mission->refresh();
    }

    /**
     * L'artisan accepte la demande de devis qui lui est destinée.
     */
    public function acceptRequest(Mission $mission, User $artisan): Mission
    {
        $this->assertAddressee($mission, $artisan);

        if (! $artisan->isKycActif()) {
            throw new MissionActionException('Votre KYC doit être validé pour accepter cette mission.', 403);
        }

        DB::transaction(function () use ($mission, $artisan): void {
            $mission->update(['artisan_rejected_at' => null]);
            $this->lifecycle->transition($mission, DraftState::class, $artisan, 'Demande de devis acceptée');
        });

        if ($mission->client) {
            $this->notificationService->notify($mission->client, 'mission.demande_acceptee.client', [], ['mission_id' => $mission->id]);
        }

        return $mission->refresh();
    }

    /**
     * L'artisan refuse la demande : la mission revient en recherche d'artisan.
     */
    public function rejectRequest(Mission $mission, User $artisan): Mission
    {
        $this->assertAddressee($mission, $artisan);

        // Éviter les conflits/litiges si un paiement est en cours ou déjà effectué
        $hasActiveTransaction = $mission->transactions()
            ->whereIn('statut', ['en_attente', 'confirme'])
            ->exists();

        if ($hasActiveTransaction) {
            throw new MissionActionException('Impossible de refuser la demande : un paiement est en cours d\'initiation ou a déjà été validé.', 400);
        }

        DB::transaction(function () use ($mission, $artisan): void {
            $mission->update([
                'artisan_id' => null,
                'artisan_rejected_at' => now(),
                'artisan_assigned_at' => null,
                'artisan_reminded_at' => null,
            ]);
            $this->lifecycle->transition($mission, DraftState::class, $artisan, 'Demande de devis refusée');
        });

        if ($mission->client) {
            $this->notificationService->notify($mission->client, 'mission.demande_refusee.client', [], ['mission_id' => $mission->id]);
        }

        return $mission->refresh();
    }

    /**
     * La propriété est contrôlée avant tout autre refus : un tiers n'apprend
     * rien de l'état de la mission.
     */
    private function assertAddressee(Mission $mission, User $artisan): void
    {
        if ((int) $mission->artisan_id !== (int) $artisan->id) {
            throw new MissionActionException('Accès refusé.', 403);
        }

        if ($mission->hasPendingDevis()) {
            throw new MissionActionException('Cette mission a un devis en cours d\'examen et ne peut pas être traitée.');
        }

        if (! $mission->status instanceof PendingArtisanAcceptanceState) {
            throw new MissionActionException('Statut invalide.', 400);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Délai de réponse de l'artisan
    // ─────────────────────────────────────────────────────────────────────

    public function artisanResponseHours(): int
    {
        return max(1, (int) Setting::getValueByKey('mission_artisan_response_hours', 24));
    }

    /**
     * Relance à mi-délai, puis retire la demande à l'artisan resté sans
     * réponse : la mission revient en recherche d'artisan.
     *
     * @return array{reminded: int, expired: int}
     */
    public function processUnansweredRequests(): array
    {
        $hours = $this->artisanResponseHours();
        $reminded = 0;
        $expired = 0;

        $pending = Mission::query()
            ->where('status', 'pending_artisan_acceptance')
            ->whereNotNull('artisan_id')
            ->whereNotNull('artisan_assigned_at');

        (clone $pending)
            ->where('artisan_assigned_at', '<=', now()->subHours($hours))
            ->with(['client', 'artisan'])
            ->each(function (Mission $mission) use ($hours, &$expired): void {
                $artisan = $mission->artisan;

                DB::transaction(function () use ($mission, $hours): void {
                    $mission->update([
                        'artisan_id' => null,
                        'artisan_assigned_at' => null,
                        'artisan_reminded_at' => null,
                    ]);
                    $this->lifecycle->transition(
                        $mission,
                        DraftState::class,
                        null,
                        "Sans réponse de l'artisan sous {$hours} heures",
                        ['automatique' => true],
                    );
                });

                if ($mission->client) {
                    $this->notificationService->notify($mission->client, 'mission.sans_reponse.client', ['heures' => $hours], ['mission_id' => $mission->id]);
                }
                if ($artisan) {
                    $this->notificationService->notify($artisan, 'mission.demande_retiree.artisan', [], ['mission_id' => $mission->id]);
                }

                $expired++;
            });

        (clone $pending)
            ->whereNull('artisan_reminded_at')
            ->where('artisan_assigned_at', '<=', now()->subMinutes($hours * 30))
            ->with('artisan')
            ->each(function (Mission $mission) use ($hours, &$reminded): void {
                $mission->update(['artisan_reminded_at' => now()]);

                if ($mission->artisan) {
                    $restant = max(1, (int) ceil($hours - $mission->artisan_assigned_at->diffInMinutes(now()) / 60));
                    $this->notificationService->notify($mission->artisan, 'mission.relance_demande.artisan', ['heures' => $restant], ['mission_id' => $mission->id]);
                }

                $reminded++;
            });

        return ['reminded' => $reminded, 'expired' => $expired];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Validation finale du chantier
    // ─────────────────────────────────────────────────────────────────────

    public function finalApprovalHours(): int
    {
        return max(1, (int) Setting::getValueByKey('mission_final_approval_hours', 72));
    }

    /**
     * Le client valide la fin du chantier : la mission est clôturée et la
     * notation s'ouvre. Aucun fonds n'est retenu à ce stade : chaque étape a
     * déjà été payée sur son code OTP.
     */
    public function approveCompletion(Mission $mission, User $client): Mission
    {
        if ((int) $mission->client_id !== (int) $client->id) {
            throw new MissionActionException('Accès refusé.', 403);
        }

        if ($mission->status instanceof CompletedState) {
            return $mission;
        }

        if (! $mission->status instanceof PendingApprovalState) {
            throw new MissionActionException('Cette mission n\'attend pas votre validation finale.');
        }

        $this->lifecycle->transition($mission, CompletedState::class, $client, 'Fin du chantier validée par le client');

        if ($mission->artisan) {
            $this->notificationService->notify($mission->artisan, 'mission.cloturee.artisan', ['mission' => $mission->id], ['mission_id' => $mission->id]);
        }

        return $mission->refresh();
    }

    /**
     * Clôture les missions restées sans validation du client au-delà du délai.
     *
     * @return array{closed: int, blocked: list<int>}
     */
    public function autoApproveCompletions(): array
    {
        $hours = $this->finalApprovalHours();
        $closed = 0;
        $blocked = [];

        Mission::query()
            ->where('status', 'pending_approval')
            ->whereNotNull('completion_requested_at')
            ->where('completion_requested_at', '<=', now()->subHours($hours))
            ->with(['client', 'artisan'])
            ->each(function (Mission $mission) use ($hours, &$closed, &$blocked): void {
                try {
                    $this->lifecycle->transition(
                        $mission,
                        CompletedState::class,
                        null,
                        "Clôture automatique : sans réponse du client sous {$hours} heures",
                        ['automatique' => true],
                    );
                } catch (\DomainException $e) {
                    // Garde non satisfaite (seuil Référent…) : la mission reste en attente.
                    $blocked[] = $mission->id;
                    Log::warning("Clôture automatique refusée pour la mission #{$mission->id} : ".$e->getMessage());

                    return;
                }

                if ($mission->client) {
                    $this->notificationService->notify($mission->client, 'mission.cloturee_auto.client', ['mission' => $mission->id], ['mission_id' => $mission->id]);
                }
                if ($mission->artisan) {
                    $this->notificationService->notify($mission->artisan, 'mission.cloturee.artisan', ['mission' => $mission->id], ['mission_id' => $mission->id]);
                }

                $closed++;
            });

        return ['closed' => $closed, 'blocked' => $blocked];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Forçage par l'administrateur
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Force l'état d'une mission. Les gardes de la machine à états
     * s'appliquent aussi à l'administrateur : une transition refusée lève
     * `MissionTransitionException`.
     */
    public function forceStatus(Mission $mission, string $status, User $admin, string $reason): Mission
    {
        if ($mission->hasPendingDevis()) {
            throw new MissionActionException('Cette mission a un devis en cours d\'examen et ne peut pas être modifiée.');
        }

        $previous = (string) $mission->status;

        $this->lifecycle->transition($mission, self::FORCEABLE_STATES[$status], $admin, $reason, ['force_admin' => true]);

        $this->audit->log(
            'mission.status.forced',
            $mission,
            ['before' => $previous, 'after' => (string) $mission->status, 'reason' => $reason],
            actor: $admin,
        );

        return $mission->refresh();
    }
}
