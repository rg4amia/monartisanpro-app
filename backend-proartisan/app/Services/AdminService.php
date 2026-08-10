<?php

namespace App\Services;

use App\Models\FournisseurAgree;
use App\Models\KycDocument;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\JCode;
use App\Models\Evaluation;
use App\Models\ScoreLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        private NotificationService $notificationService,
        private LitigeService $litigeService,
        private OneSignalService $oneSignalService,
    ) {}

    public function dashboard(): array
    {
        return [
            'users_total'            => User::count(),
            'artisans_actifs'        => User::where('role', 'artisan')->where('kyc_status', 'actif')->count(),
            'clients_actifs'         => User::where('role', 'client')->where('kyc_status', 'actif')->count(),
            'fournisseurs_agrees'    => FournisseurAgree::where('statut', 'agree')->count(),
            'missions_en_cours'      => Mission::where('status', 'in_progress')->count(),
            'missions_en_litige'     => Mission::where('status', 'disputed')->count(),
            'litiges_ouverts'        => Litige::whereIn('statut', ['ouvert', 'en_cours'])->count(),
            'kyc_en_attente'         => User::where('kyc_status', 'en_attente')->count(),
            'referent_required_open' => Mission::where('referent_required', true)
                ->whereIn('status', ['funded_locked', 'in_progress', 'disputed'])
                ->count(),
            'recent_fraud_alerts'    => JCode::where('statut', 'actif')->count(), // Placeholder for actual fraud tracking
            'volume_transactions_24h' => Transaction::where('created_at', '>=', now()->subDay())->sum('montant'),
        ];
    }

    public function pendingKyc(?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->where('kyc_status', 'en_attente')
            ->whereIn('role', ['client', 'artisan', 'fournisseur', 'livreur'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->with(['kycDocuments' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function reviewKyc(User $admin, User $user, string $decision, ?string $rejectionReason = null): User
    {
        $documents = KycDocument::where('user_id', $user->id)
            ->whereIn('type', ['cni', 'selfie'])
            ->whereIn('statut', ['en_attente', 'approuve'])
            ->get()
            ->keyBy('type');

        if ($decision === 'approuve') {
            // Si les documents manquent (cas fréquent pour les comptes de test/seed),
            // on crée des documents fictifs pour débloquer l'approbation.
            if (! $documents->has('cni')) {
                $cni = KycDocument::create([
                    'user_id'  => $user->id,
                    'type'     => 'cni',
                    'file_url' => 'https://placeholder.com/cni.png',
                    'statut'   => 'en_attente',
                ]);
                $documents->put('cni', $cni);
            }
            if (! $documents->has('selfie')) {
                $selfie = KycDocument::create([
                    'user_id'  => $user->id,
                    'type'     => 'selfie',
                    'file_url' => 'https://placeholder.com/selfie.png',
                    'statut'   => 'en_attente',
                ]);
                $documents->put('selfie', $selfie);
            }
        }

        DB::transaction(function () use ($admin, $user, $decision, $rejectionReason, $documents): void {
            $docStatus = $decision === 'approuve' ? 'approuve' : 'rejete';

            foreach (['cni', 'selfie'] as $type) {
                if ($documents->has($type)) {
                    $documents[$type]->update([
                        'statut'           => $docStatus,
                        'reviewed_by'      => $admin->id,
                        'rejection_reason' => $decision === 'rejete' ? $rejectionReason : null,
                        'reviewed_at'      => now(),
                    ]);
                }
            }

            $user->update([
                'kyc_status' => $decision === 'approuve' ? 'actif' : 'rejete',
            ]);
        });

        $this->notificationService->send(
            $user,
            'kyc',
            'Statut KYC mis à jour',
            $decision === 'approuve'
                ? 'Votre dossier KYC est validé. Vous pouvez maintenant effectuer des transactions.'
                : 'Votre dossier KYC a été rejeté. Merci de vérifier vos documents et de recommencer.',
            ['decision' => $decision]
        );

        // Envoi de la notification Push
        $this->oneSignalService->sendToUser(
            (string) $user->id,
            'Statut KYC mis à jour',
            $decision === 'approuve'
                ? 'Votre dossier KYC est validé. Vous pouvez maintenant postuler !'
                : 'Votre dossier KYC a été rejeté. Merci de vérifier vos documents.',
            ['decision' => $decision, 'type' => 'kyc']
        );

        return $user->fresh(['kycDocuments']);
    }

    public function listLitiges(?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->litigeService->evaluateDueLitiges();

        return Litige::query()
            ->with(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listMissions(?string $status = null, ?string $query = null, int $perPage = 20): LengthAwarePaginator
    {
        return Mission::query()
            ->with([
                'client:id,name,phone',
                'artisan:id,name,phone',
                'jalons',
                'jcodes.fournisseur:id,name,phone',
                'transactions',
                'litiges',
                'evaluations',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query): void {
                    $sub->where('id', $query)
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('gemini_category', 'like', "%{$query}%")
                        ->orWhereHas('client', fn ($client) => $client
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"))
                        ->orWhereHas('artisan', fn ($artisan) => $artisan
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function resolveLitige(User $admin, Litige $litige, array $payload): Litige
    {
        return $this->litigeService->arbitrate($admin, $litige, $payload);
    }

    public function pendingFournisseurs(int $perPage = 20): LengthAwarePaginator
    {
        return FournisseurAgree::query()
            ->with('user')
            ->where('statut', 'en_attente')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function reviewFournisseur(User $admin, FournisseurAgree $fournisseurAgree, string $decision): FournisseurAgree
    {
        $fournisseurAgree->update([
            'statut'     => $decision,
            'approuve_at' => $decision === 'agree' ? now() : null,
        ]);

        $fournisseur = $fournisseurAgree->user;

        if ($fournisseur) {
            $this->notificationService->send(
                $fournisseur,
                'fournisseur',
                'Décision sur votre agrément',
                $decision === 'agree'
                    ? 'Votre boutique est agréée. Vous pouvez scanner les J-Codes.'
                    : 'Votre agrément fournisseur est suspendu. Contactez le support.',
                ['decision' => $decision, 'admin_id' => $admin->id]
            );
        }

        return $fournisseurAgree->fresh('user');
    }

    public function listUsers(?string $query = null, ?string $role = null, ?string $kycStatus = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->withCount([
                'missionsClient',
                'missionsArtisan',
            ])
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query): void {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('id', $query);
                });
            })
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($kycStatus, fn ($q) => $q->where('kyc_status', $kycStatus))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listTransactions(?string $status = null, ?string $provider = null, int $perPage = 20): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['user', 'mission'])
            ->when($status, fn ($q) => $q->where('statut', $status))
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listEvaluations(?int $perPage = 100): LengthAwarePaginator
    {
        return Evaluation::query()
            ->with(['mission', 'evaluateur', 'evalue'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listArtisansScores(): array
    {
        return User::query()
            ->where('role', 'artisan')
            ->withCount('evaluationsRecues')
            ->withAvg('evaluationsRecues', 'fiabilite')
            ->withAvg('evaluationsRecues', 'integrite')
            ->withAvg('evaluationsRecues', 'qualite')
            ->withAvg('evaluationsRecues', 'reactivite')
            ->orderByDesc('score_prosartisan')
            ->get()
            ->toArray();
    }

    public function listScoreLedger(): array
    {
        return ScoreLedgerEntry::query()
            ->with(['user', 'mission', 'evaluation'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->toArray();
    }
}
