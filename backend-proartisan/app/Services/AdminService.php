<?php

namespace App\Services;

use App\Models\FournisseurAgree;
use App\Models\KycDocument;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(private NotificationService $notificationService) {}

    public function dashboard(): array
    {
        return [
            'users_total'            => User::count(),
            'artisans_actifs'        => User::where('role', 'artisan')->where('kyc_status', 'actif')->count(),
            'clients_actifs'         => User::where('role', 'client')->where('kyc_status', 'actif')->count(),
            'fournisseurs_agrees'    => FournisseurAgree::where('statut', 'agree')->count(),
            'missions_en_cours'      => Mission::where('status', 'en_cours')->count(),
            'missions_en_litige'     => Mission::where('status', 'litige')->count(),
            'litiges_ouverts'        => Litige::whereIn('statut', ['ouvert', 'en_cours'])->count(),
            'kyc_en_attente'         => User::where('kyc_status', 'en_attente')->count(),
            'referent_required_open' => Mission::where('referent_required', true)
                ->whereIn('status', ['financee', 'en_cours', 'litige'])
                ->count(),
        ];
    }

    public function pendingKyc(?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->where('kyc_status', 'en_attente')
            ->whereIn('role', ['client', 'artisan', 'fournisseur'])
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

        if (! $documents->has('cni') || ! $documents->has('selfie')) {
            throw ValidationException::withMessages([
                'user' => 'Les deux documents KYC (CNI et selfie) sont requis avant validation.',
            ]);
        }

        DB::transaction(function () use ($admin, $user, $decision, $rejectionReason, $documents): void {
            $docStatus = $decision === 'approuve' ? 'approuve' : 'rejete';

            foreach (['cni', 'selfie'] as $type) {
                $documents[$type]->update([
                    'statut'           => $docStatus,
                    'reviewed_by'      => $admin->id,
                    'rejection_reason' => $decision === 'rejete' ? $rejectionReason : null,
                    'reviewed_at'      => now(),
                ]);
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

        return $user->fresh(['kycDocuments']);
    }

    public function listLitiges(?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        return Litige::query()
            ->with(['mission.client', 'mission.artisan', 'declencheur'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function resolveLitige(User $admin, Litige $litige, string $decision): Litige
    {
        DB::transaction(function () use ($litige, $decision): void {
            $litige->update([
                'statut'   => 'resolu',
                'decision' => $decision,
                'resolu_at' => now(),
            ]);

            $missionStatus = $decision === 'gel' ? 'litige' : 'en_cours';
            $litige->mission->update(['status' => $missionStatus]);
        });

        $litige = $litige->fresh(['mission.client', 'mission.artisan', 'declencheur']);

        $this->notificationService->sendAdmin(
            'litige',
            'Litige arbitré',
            "Le litige #{$litige->id} a été clôturé avec la décision: {$decision}.",
            ['litige_id' => $litige->id, 'decision' => $decision, 'admin_id' => $admin->id]
        );

        if ($litige->mission->client) {
            $this->notificationService->send(
                $litige->mission->client,
                'litige',
                'Décision de litige rendue',
                "Le litige de la mission #{$litige->mission_id} a été arbitré. Décision: {$decision}.",
                ['litige_id' => $litige->id, 'decision' => $decision]
            );
        }

        if ($litige->mission->artisan) {
            $this->notificationService->send(
                $litige->mission->artisan,
                'litige',
                'Décision de litige rendue',
                "Le litige de la mission #{$litige->mission_id} a été arbitré. Décision: {$decision}.",
                ['litige_id' => $litige->id, 'decision' => $decision]
            );
        }

        return $litige;
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
}
