<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\EvidenceVault;
use App\Models\JCode;
use App\Models\Litige;
use App\Models\LitigeEvidence;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LitigeService
{
    public function __construct(
        private NotificationService $notificationService,
        private WalletService $walletService,
        private PhotoService $photoService,
        private JCodeService $jCodeService,
        private PdfService $pdfService,
    ) {}

    public function paginateForUser(User $user, ?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->evaluateDueLitiges();

        $query = Litige::query()
            ->with([
                'declencheur',
                'mission.client',
                'mission.artisan',
                'preuves.user',
            ])
            ->when($statut, fn($q) => $q->where('statut', $statut))
            ->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $query->whereHas('mission', function ($missionQuery) use ($user): void {
                $missionQuery
                    ->where('client_id', $user->id)
                    ->orWhere('artisan_id', $user->id);
            });
        }

        return $query->paginate($perPage);
    }

    public function open(User $user, Mission $mission, array $data): Litige
    {
        $this->ensureMissionParticipant($mission, $user);

        if ((string) $mission->status === 'draft') {
            throw ValidationException::withMessages([
                'mission_id' => ['Un litige ne peut être ouvert qu’après financement ou exécution de la mission.'],
            ]);
        }

        $existing = $mission->litiges()
            ->whereIn('statut', ['ouvert', 'en_cours'])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing->load(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user']);
        }

        $litige = DB::transaction(function () use ($user, $mission, $data) {
            $mission->update([
                'status' => \App\States\Mission\DisputedState::class,
                'funds_frozen' => true,
            ]);

            return Litige::create([
                'mission_id' => $mission->id,
                'declencheur_id' => $user->id,
                'type' => $mission->client_id === $user->id ? 'client' : 'artisan',
                'motif' => trim((string) ($data['motif'] ?? 'Autre')),
                'description' => $data['description'],
                'statut' => 'ouvert',
                'workflow_step' => 'preuves',
                'funds_locked_at' => now(),
                'evidence_deadline_at' => now()->addHours(48),
                'arbitration_deadline_at' => now()->addHours(72),
                'resolution_payload' => [
                    'opened_by_role' => $mission->client_id === $user->id ? 'client' : 'artisan',
                ],
            ]);
        });

        $counterparty = $mission->client_id === $user->id ? $mission->artisan : $mission->client;
        if ($counterparty) {
            $this->notificationService->send(
                $counterparty,
                'litige',
                'Alerte litige',
                'Un litige a été ouvert sur votre mission. Les fonds sont gelés jusqu’à décision.',
                ['litige_id' => $litige->id, 'mission_id' => $mission->id]
            );
        }

        $this->notificationService->sendAdmin(
            'litige',
            'Nouveau litige à instruire',
            "Le litige #{$litige->id} a été ouvert sur la mission #{$mission->id}.",
            ['litige_id' => $litige->id, 'mission_id' => $mission->id]
        );

        return $litige->load(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user']);
    }

    public function storeEvidence(Litige $litige, User $user, array $data): Litige
    {
        $litige->loadMissing(['mission.client', 'mission.artisan', 'preuves.user']);

        if ($litige->isResolved()) {
            throw ValidationException::withMessages([
                'litige' => ['Ce litige est déjà clôturé.'],
            ]);
        }

        if ($litige->workflow_step !== 'preuves') {
            throw ValidationException::withMessages([
                'litige' => ['La phase de dépôt des preuves est terminée.'],
            ]);
        }

        $partie = $this->resolveParty($litige->mission, $user);
        if ($partie === null) {
            throw ValidationException::withMessages([
                'litige' => ['Vous n’êtes pas autorisé à déposer des preuves sur ce litige.'],
            ]);
        }

        DB::transaction(function () use ($litige, $user, $data, $partie): void {
            foreach ($data['photos'] as $index => $photoInput) {
                $uploaded = $this->photoService->uploadGeolocatedPhoto(
                    $photoInput['photo'],
                    (float) $photoInput['latitude'],
                    (float) $photoInput['longitude'],
                    'litige',
                    $litige->id
                );

                // ── Evidence Vault : intégrité SHA-256 ──
                $fileHash = hash_file('sha256', $photoInput['photo']->getRealPath());

                $existingVaultEntry = EvidenceVault::where('sha256_hash', $fileHash)
                    ->where('litige_id', $litige->id)
                    ->first();

                if ($existingVaultEntry) {
                    throw ValidationException::withMessages([
                        'photos' => ["Doublon détecté : le fichier (index {$index}) a déjà été déposé pour ce litige."],
                    ]);
                }

                EvidenceVault::create([
                    'litige_id'   => $litige->id,
                    'uploaded_by' => $user->id,
                    'file_url'    => $uploaded['url'],
                    'sha256_hash' => $fileHash,
                    'ip_address'  => request()?->ip(),
                    'uploaded_at' => now(),
                ]);

                LitigeEvidence::create([
                    'litige_id' => $litige->id,
                    'user_id' => $user->id,
                    'partie' => $partie,
                    'description' => $photoInput['description'] ?? null,
                    'media_url' => $uploaded['url'],
                    'media_path' => $uploaded['path'],
                    'latitude' => $uploaded['latitude'],
                    'longitude' => $uploaded['longitude'],
                    'taken_at' => $uploaded['taken_at'],
                    'metadata' => ['index' => $index],
                ]);
            }

            $litige->unsetRelation('preuves');
            $clientReady = $this->proofCount($litige, 'client') >= 2;
            $artisanReady = $this->proofCount($litige, 'artisan') >= 1;

            $updates = [];
            if ($partie === 'client' && $clientReady) {
                $updates['client_evidence_submitted_at'] = $litige->client_evidence_submitted_at ?? now();
            }

            if ($partie === 'artisan' && $artisanReady) {
                $updates['artisan_evidence_submitted_at'] = $litige->artisan_evidence_submitted_at ?? now();
            }

            if ($clientReady && $artisanReady) {
                $updates['statut'] = 'en_cours';
                $updates['workflow_step'] = 'arbitrage';
                $updates['arbitration_started_at'] = $litige->arbitration_started_at ?? now();
            }

            if ($updates !== []) {
                $litige->update($updates);
            }
        });

        $litige->refresh()->load(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user']);

        if ($litige->workflow_step === 'arbitrage') {
            $this->notificationService->sendAdmin(
                'litige',
                'Litige prêt pour arbitrage',
                "Le litige #{$litige->id} dispose désormais des preuves des deux parties.",
                ['litige_id' => $litige->id, 'mission_id' => $litige->mission_id]
            );
        }

        return $litige;
    }

    public function evaluateSla(Litige $litige): Litige
    {
        $litige->loadMissing(['mission.client', 'mission.artisan', 'preuves.user']);

        if ($litige->isResolved()) {
            return $litige;
        }

        $clientProofCount = $this->proofCount($litige, 'client');
        $artisanProofCount = $this->proofCount($litige, 'artisan');

        if ($litige->workflow_step === 'preuves' && $clientProofCount >= 2 && $artisanProofCount >= 1) {
            $litige->update([
                'statut' => 'en_cours',
                'workflow_step' => 'arbitrage',
                'arbitration_started_at' => $litige->arbitration_started_at ?? now(),
                'client_evidence_submitted_at' => $litige->client_evidence_submitted_at ?? now(),
                'artisan_evidence_submitted_at' => $litige->artisan_evidence_submitted_at ?? now(),
            ]);

            return $litige->fresh(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user']);
        }

        if (
            $litige->workflow_step === 'preuves'
            && $litige->evidence_deadline_at !== null
            && $litige->evidence_deadline_at->isPast()
        ) {
            $systemAdmin = User::query()->where('role', 'admin')->orderBy('id')->first();

            if ($artisanProofCount < 1) {
                return $this->arbitrate(
                    $systemAdmin,
                    $litige,
                    [
                        'decision' => 'client',
                        'notes' => 'Résolution automatique SLA: absence de réponse de l’artisan sous 48h.',
                        'refund_materiaux' => $this->walletService->getMissionEscrowBalance($litige->mission, WalletType::WALLET_MATERIAUX),
                        'refund_mo' => $this->walletService->getMissionEscrowBalance($litige->mission, WalletType::WALLET_MO),
                        'resolution_reason' => 'absence_preuves_artisan',
                    ]
                );
            }

            if ($clientProofCount < 2) {
                return $this->arbitrate(
                    $systemAdmin,
                    $litige,
                    [
                        'decision' => 'artisan',
                        'notes' => 'Résolution automatique SLA: preuves client insuffisantes sous 48h.',
                        'release_materiaux' => $this->walletService->getMissionEscrowBalance($litige->mission, WalletType::WALLET_MATERIAUX),
                        'release_mo' => $this->walletService->getMissionEscrowBalance($litige->mission, WalletType::WALLET_MO),
                        'resolution_reason' => 'absence_preuves_client',
                    ]
                );
            }
        }

        return $litige;
    }

    public function evaluateDueLitiges(): void
    {
        Litige::query()
            ->whereIn('statut', ['ouvert', 'en_cours'])
            ->where('workflow_step', 'preuves')
            ->whereNotNull('evidence_deadline_at')
            ->where('evidence_deadline_at', '<=', now())
            ->get()
            ->each(fn(Litige $litige) => $this->evaluateSla($litige));
    }

    public function arbitrate(?User $admin, Litige $litige, array $payload): Litige
    {
        $litige->loadMissing(['mission.client', 'mission.artisan', 'mission.jcodes', 'declencheur', 'preuves.user']);

        if ($admin !== null && $admin->role !== 'admin') {
            throw ValidationException::withMessages([
                'admin' => ['Seul un administrateur peut arbitrer un litige.'],
            ]);
        }

        if ($litige->isResolved()) {
            throw ValidationException::withMessages([
                'litige' => ['Ce litige est déjà clôturé.'],
            ]);
        }

        $decision = $payload['decision'];
        $mission = $litige->mission;
        $remainingMateriaux = $this->walletService->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX);
        $remainingMo = $this->walletService->getMissionEscrowBalance($mission, WalletType::WALLET_MO);

        $refundMateriaux = min((int) ($payload['refund_materiaux'] ?? $this->defaultRefundMateriaux($decision, $remainingMateriaux)), $remainingMateriaux);
        $refundMo = min((int) ($payload['refund_mo'] ?? $this->defaultRefundMo($decision, $remainingMo)), $remainingMo);
        $releaseMateriaux = min((int) ($payload['release_materiaux'] ?? $this->defaultReleaseMateriaux($decision, $remainingMateriaux)), $remainingMateriaux);
        $releaseMo = min((int) ($payload['release_mo'] ?? $this->defaultReleaseMo($decision, $remainingMo)), $remainingMo);
        $resolutionReason = $payload['resolution_reason'] ?? 'arbitrage_admin';

        $invoicePath = null;
        if ($decision === 'artisan') {
            $invoicePath = $this->pdfService->generateDisbursementInvoice($mission, $releaseMo + $releaseMateriaux);
        }

        DB::transaction(function () use (
            $litige,
            $mission,
            $admin,
            $decision,
            $refundMateriaux,
            $refundMo,
            $releaseMateriaux,
            $releaseMo,
            $payload,
            $resolutionReason,
            $invoicePath
        ): void {
            if ($decision === 'client') {
                $this->walletService->refundClientFromDispute($mission, $refundMateriaux, $refundMo, $litige);
                $mission->update([
                    'status' => \App\States\Mission\CancelledState::class,
                    'funds_frozen' => false,
                ]);
            } elseif ($decision === 'artisan') {
                $this->walletService->releaseLaborEscrowToArtisan($mission, $releaseMo, $litige, true);
                $this->walletService->releaseMaterialEscrowToArtisan($mission, $releaseMateriaux, $litige);
                $mission->update([
                    'status' => \App\States\Mission\CompletedState::class,
                    'funds_frozen' => false,
                ]);
            } elseif ($decision === 'mixte') {
                $this->settleSupplierPaymentsForMission($mission);
                $this->walletService->refundClientFromDispute($mission, $refundMateriaux, $refundMo, $litige);

                $remainingMateriauxAfterRefund = $this->walletService->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX);
                if ($remainingMateriauxAfterRefund > 0) {
                    $this->walletService->releaseMaterialEscrowToArtisan($mission, $remainingMateriauxAfterRefund, $litige);
                }

                $mission->update([
                    'status' => \App\States\Mission\CompletedState::class,
                    'funds_frozen' => false,
                ]);
            } else {
                $mission->update([
                    'status' => \App\States\Mission\DisputedState::class,
                    'funds_frozen' => true,
                ]);
            }

            $litige->update([
                'decision' => $decision,
                'statut' => $decision === 'gel' ? 'en_cours' : 'resolu',
                'workflow_step' => $decision === 'gel' ? 'visite_referent' : 'resolu',
                'resolu_at' => $decision === 'gel' ? null : now(),
                'resolved_by' => $admin?->id,
                'admin_notes' => $payload['notes'] ?? null,
                'resolution_reason' => $resolutionReason,
                'resolution_payload' => [
                    'refund_materiaux' => $refundMateriaux,
                    'refund_mo' => $refundMo,
                    'release_materiaux' => $releaseMateriaux,
                    'release_mo' => $releaseMo,
                    'decided_at' => now()->toIso8601String(),
                    'invoice_path' => $invoicePath,
                ],
            ]);
        });

        $litige = $litige->fresh(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user', 'resolvedBy']);

        if ($decision !== 'gel') {
            $sanctions = $this->applySanctions($litige);
            $litige->update(['sanctions_json' => $sanctions]);
        } else {
            $this->notifyReferents($litige);
        }

        $this->notifyDecision($litige);

        return $litige->fresh(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user', 'resolvedBy']);
    }

    private function ensureMissionParticipant(Mission $mission, User $user): void
    {
        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'mission_id' => ['Accès refusé à cette mission.'],
            ]);
        }
    }

    private function resolveParty(Mission $mission, User $user): ?string
    {
        return match (true) {
            $mission->client_id === $user->id => 'client',
            $mission->artisan_id === $user->id => 'artisan',
            default => null,
        };
    }

    private function proofCount(Litige $litige, string $partie): int
    {
        if ($litige->relationLoaded('preuves')) {
            return $litige->preuves->where('partie', $partie)->count();
        }

        return $litige->preuves()->where('partie', $partie)->count();
    }

    private function defaultRefundMateriaux(string $decision, int $remainingMateriaux): int
    {
        return match ($decision) {
            'client' => $remainingMateriaux,
            'mixte' => 0,
            default => 0,
        };
    }

    private function defaultRefundMo(string $decision, int $remainingMo): int
    {
        return match ($decision) {
            'client', 'mixte' => $remainingMo,
            default => 0,
        };
    }

    private function defaultReleaseMateriaux(string $decision, int $remainingMateriaux): int
    {
        return match ($decision) {
            'artisan' => $remainingMateriaux,
            default => 0,
        };
    }

    private function defaultReleaseMo(string $decision, int $remainingMo): int
    {
        return match ($decision) {
            'artisan' => $remainingMo,
            default => 0,
        };
    }

    private function settleSupplierPaymentsForMission(Mission $mission): void
    {
        $mission->loadMissing('jcodes.fournisseur');

        $mission->jcodes
            ->where('statut', 'utilise')
            ->filter(fn(JCode $jcode) => $jcode->paiement_status !== 'paye')
            ->each(fn(JCode $jcode) => $this->jCodeService->settleSupplierPayment($jcode, true));
    }

    private function applySanctions(Litige $litige): array
    {
        $losers = collect();
        if ($litige->decision === 'client') {
            $losers->push($litige->mission->artisan);
        } elseif ($litige->decision === 'artisan') {
            $losers->push($litige->mission->client);
        } elseif ($litige->decision === 'mixte') {
            $losers->push($litige->mission->artisan);
        }

        $sanctions = [];

        $losers
            ->filter()
            ->each(function (User $user) use (&$sanctions, $litige): void {
                $previousScore = $user->score_nzassa;
                // Création d'une entrée de Ledger pour litige perdu
                // Décision client = perte totale de l'artisan. Si la mission a été commencée et le client remboursé,
                // c'est soit un abandon (-300 pts), soit une malfaçon/fraude (-150 pts).
                // Utilisons la fraude (-150 pts) comme pénalité standard de litige perdu,
                // et abandon (-300 pts) si c'est spécifié ou si c'est le 3ème litige.
                $lostCount = Litige::query()
                    ->where('statut', 'resolu')
                    ->where('resolu_at', '>=', now()->subMonths(6))
                    ->where(function ($query) use ($user): void {
                        $query
                            ->where(function ($clientQuery) use ($user): void {
                                $clientQuery->where('decision', 'client')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('artisan_id', $user->id));
                            })
                            ->orWhere(function ($artisanQuery) use ($user): void {
                                $artisanQuery->where('decision', 'artisan')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('client_id', $user->id));
                            })
                            ->orWhere(function ($mixedQuery) use ($user): void {
                                $mixedQuery->where('decision', 'mixte')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('artisan_id', $user->id));
                            });
                    })
                    ->count();

                $penaltyPoints = ($lostCount >= 2) ? -300 : -150;
                $eventType = ($lostCount >= 2) ? 'dispute_abandon' : 'dispute_fraud';

                if ($user->role === 'artisan') {
                    \App\Models\ScoreLedgerEntry::create([
                        'user_id' => $user->id,
                        'event_type' => $eventType,
                        'points' => $penaltyPoints,
                        'credibility_factor' => 1.00,
                        'evaluation_id' => null,
                        'mission_id' => $litige->mission_id,
                        'description' => "Pénalité litige perdu (Décision: {$litige->decision})",
                    ]);
                    app(ScoreService::class)->recalculateFromLedger($user);

                    // Caution penalty for sponsor (parrain)
                    $parrainage = \App\Models\Parrainage::where('filleul_id', $user->id)->first();
                    if ($parrainage) {
                        $parrain = $parrainage->parrain;
                        if ($parrain) {
                            \App\Models\ScoreLedgerEntry::create([
                                'user_id' => $parrain->id,
                                'event_type' => 'dispute_fraud',
                                'points' => -50,
                                'credibility_factor' => 1.00,
                                'evaluation_id' => null,
                                'mission_id' => $litige->mission_id,
                                'description' => "Pénalité parrainage : Filleul #{$user->id} a perdu un litige.",
                            ]);
                            app(ScoreService::class)->recalculateFromLedger($parrain);
                        }
                    }
                } else {
                    $user->update([
                        'score_nzassa' => max(0, $previousScore - 1),
                    ]);
                }

                $lostCount = Litige::query()
                    ->where('statut', 'resolu')
                    ->where('resolu_at', '>=', now()->subMonths(6))
                    ->where(function ($query) use ($user): void {
                        $query
                            ->where(function ($clientQuery) use ($user): void {
                                $clientQuery->where('decision', 'client')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('artisan_id', $user->id));
                            })
                            ->orWhere(function ($artisanQuery) use ($user): void {
                                $artisanQuery->where('decision', 'artisan')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('client_id', $user->id));
                            })
                            ->orWhere(function ($mixedQuery) use ($user): void {
                                $mixedQuery->where('decision', 'mixte')
                                    ->whereHas('mission', fn($missionQuery) => $missionQuery->where('artisan_id', $user->id));
                            });
                    })
                    ->count();

                $accountStatus = $user->account_status;
                $reason = $user->account_status_reason;

                if ($user->role === 'client' && $lostCount >= 3) {
                    $accountStatus = 'suspendu';
                    $reason = 'Compte bloqué pour litiges abusifs répétés sur 6 mois.';
                }

                if (in_array($user->role, ['artisan', 'fournisseur'], true) && $lostCount >= 3) {
                    $accountStatus = 'banni';
                    $reason = 'Bannissement automatique après trois litiges perdus sur 6 mois.';
                }

                if ($accountStatus !== $user->account_status || $reason !== $user->account_status_reason) {
                    $user->update([
                        'account_status' => $accountStatus,
                        'account_status_reason' => $reason,
                        'blocked_at' => $accountStatus === 'actif' ? null : now(),
                    ]);
                }

                $sanctions[] = [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'score_before' => $previousScore,
                    'score_after' => $user->fresh()->score_nzassa,
                    'lost_disputes_last_6_months' => $lostCount,
                    'account_status' => $user->fresh()->account_status,
                    'reason' => $user->fresh()->account_status_reason,
                ];
            });

        return $sanctions;
    }

    private function notifyDecision(Litige $litige): void
    {
        $label = match ($litige->decision) {
            'client' => 'Remboursement client',
            'artisan' => 'Paiement artisan',
            'mixte' => 'Décision mixte',
            'gel' => 'Gel et visite référent',
            default => 'Décision de litige',
        };

        if ($litige->mission->client) {
            $this->notificationService->send(
                $litige->mission->client,
                'litige',
                'Décision de litige rendue',
                "Le litige #{$litige->id} a été traité: {$label}.",
                ['litige_id' => $litige->id, 'decision' => $litige->decision]
            );
        }

        if ($litige->mission->artisan) {
            $this->notificationService->send(
                $litige->mission->artisan,
                'litige',
                'Décision de litige rendue',
                "Le litige #{$litige->id} a été traité: {$label}.",
                ['litige_id' => $litige->id, 'decision' => $litige->decision]
            );
        }

        $this->notificationService->sendAdmin(
            'litige',
            'Litige mis à jour',
            "Le litige #{$litige->id} est désormais au statut {$litige->statut}.",
            ['litige_id' => $litige->id, 'decision' => $litige->decision]
        );
    }

    public function assignJury(Litige $litige): void
    {
        $litige->loadMissing(['mission.artisan.artisanProfile']);

        $artisanTradeId = $litige->mission->artisan->artisanProfile?->trade_id;

        $jurorsQuery = User::query()
            ->where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->where('id', '!=', $litige->mission->artisan_id)
            ->where('score_nzassa', '>', 800);

        if ($artisanTradeId) {
            $jurorsQuery->whereHas('artisanProfile', function ($q) use ($artisanTradeId) {
                $q->where('trade_id', $artisanTradeId);
            });
        }

        $jurors = $jurorsQuery->inRandomOrder()->limit(3)->get();

        if ($jurors->count() < 3) {
            $jurors = User::query()
                ->where('role', 'artisan')
                ->where('kyc_status', 'actif')
                ->where('id', '!=', $litige->mission->artisan_id)
                ->where('score_nzassa', '>', 800)
                ->inRandomOrder()
                ->limit(3)
                ->get();
        }

        if ($jurors->count() < 3) {
            $jurors = User::query()
                ->where('role', 'artisan')
                ->where('kyc_status', 'actif')
                ->where('id', '!=', $litige->mission->artisan_id)
                ->inRandomOrder()
                ->limit(3)
                ->get();
        }

        foreach ($jurors as $juror) {
            \App\Models\JuryReview::create([
                'litige_id' => $litige->id,
                'jure_id' => $juror->id,
                'compensation' => 1500,
            ]);

            $this->notificationService->send(
                $juror,
                'jury_assignment',
                'Arbitrage N\'Zassa requis',
                "Vous avez été sélectionné comme juré pour évaluer de manière anonyme le litige #{$litige->id}.",
                ['litige_id' => $litige->id]
            );
        }

        $litige->update([
            'workflow_step' => 'jury',
            'statut' => 'en_cours',
        ]);
    }

    public function submitJuryVote(Litige $litige, User $jure, string $verdict): void
    {
        $review = \App\Models\JuryReview::where('litige_id', $litige->id)
            ->where('jure_id', $jure->id)
            ->first();

        if (!$review) {
            throw ValidationException::withMessages([
                'jury' => ['Vous n\'êtes pas assigné comme juré pour ce litige.'],
            ]);
        }

        if ($review->voted_at !== null) {
            throw ValidationException::withMessages([
                'jury' => ['Vous avez déjà voté pour ce litige.'],
            ]);
        }

        $review->update([
            'verdict' => $verdict,
            'voted_at' => now(),
        ]);

        $jure->increment('wallet_mo', $review->compensation);

        $votes = \App\Models\JuryReview::where('litige_id', $litige->id)
            ->whereNotNull('verdict')
            ->get();

        if ($votes->count() === 3) {
            $conformeCount = $votes->where('verdict', 'CONFORME')->count();
            $nonConformeCount = $votes->where('verdict', 'NON_CONFORME')->count();

            $decision = $conformeCount >= 2 ? 'artisan' : 'client';

            $this->arbitrate(null, $litige, [
                'decision' => $decision,
                'notes' => 'Résolution automatique par consensus du Jury N\'Zassa (Votes: ' . $conformeCount . ' CONFORME, ' . $nonConformeCount . ' NON_CONFORME).',
                'resolution_reason' => $decision === 'artisan' ? 'jury_consensual_conforme' : 'jury_consensual_non_conforme',
            ]);
        }
    }

    private function notifyReferents(Litige $litige): void
    {
        User::query()
            ->where('role', 'referent')
            ->get()
            ->each(function (User $referent) use ($litige): void {
                $this->notificationService->send(
                    $referent,
                    'litige',
                    'Visite référent requise',
                    "Le litige #{$litige->id} nécessite une visite terrain avant clôture.",
                    ['litige_id' => $litige->id, 'mission_id' => $litige->mission_id]
                );
            });
    }
}
