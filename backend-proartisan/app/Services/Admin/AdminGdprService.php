<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\Evaluation;
use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\Parrainage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Chantier C6 (P2-11) — conformité RGPD du backoffice.
 *
 * Regroupe la consultation des données personnelles d'un utilisateur (droit
 * d'accès et de portabilité) et leur anonymisation tracée (droit à l'effacement).
 *
 * L'anonymisation ne supprime pas la ligne `users` : les écritures financières
 * (`wallet_transactions`, `transactions`) et le journal d'audit doivent rester
 * cohérents. Elle expurge en revanche toute donnée identifiante.
 */
class AdminGdprService
{
    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * Instantané structuré de toutes les données personnelles détenues sur un
     * utilisateur, pour affichage backoffice et export de portabilité.
     *
     * @return array<string, mixed>
     */
    public function personalData(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'kyc_status' => $user->kyc_status,
                'account_status' => $user->account_status,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'cgu_accepted_at' => optional($user->cgu_accepted_at)->toIso8601String(),
                'anonymized_at' => optional($user->anonymized_at)->toIso8601String(),
                'payment_phone' => $user->payment_phone,
                'cnmci_number' => $user->cnmci_number,
                'cnmci_card_url' => $user->cnmci_card_url,
                'device_fingerprint' => $user->device_fingerprint,
                'commune' => $user->commune?->name,
            ],
            'position' => $user->getPositionCoords(),
            'kyc_documents' => $user->kycDocuments()
                ->get(['id', 'type', 'statut', 'file_url', 'created_at'])
                ->map(fn (KycDocument $doc) => [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'status' => $doc->statut,
                    'file_url' => $doc->file_url,
                    'created_at' => optional($doc->created_at)->toIso8601String(),
                ])
                ->all(),
            'evaluations_given' => Evaluation::where('evaluateur_id', $user->id)->count(),
            'evaluations_received' => Evaluation::where('evalue_id', $user->id)->count(),
            'missions_as_client' => $user->missionsClient()->count(),
            'missions_as_artisan' => $user->missionsArtisan()->count(),
            'transactions_count' => Transaction::where('user_id', $user->id)->count(),
            'notifications_count' => Notification::where('user_id', $user->id)->count(),
            'parrainages_count' => Parrainage::where('parrain_id', $user->id)
                ->orWhere('filleul_id', $user->id)
                ->count(),
            'activity_trace' => AdminActivityLog::query()
                ->where(function ($q) use ($user): void {
                    $q->where(fn ($s) => $s->where('subject_type', User::class)->where('subject_id', $user->id))
                        ->orWhere('admin_id', $user->id);
                })
                ->latest('created_at')
                ->limit(50)
                ->get(['action', 'ip_address', 'created_at'])
                ->map(fn (AdminActivityLog $log) => [
                    'action' => $log->action,
                    'ip_address' => $log->ip_address,
                    'created_at' => optional($log->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Anonymise définitivement un compte. Irréversible.
     */
    public function anonymize(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new \LogicException('Vous ne pouvez pas anonymiser votre propre compte.');
        }

        if ($user->anonymized_at !== null) {
            throw new \LogicException('Ce compte est déjà anonymisé.');
        }

        DB::transaction(function () use ($user, $actor) {
            // Suppression des pièces justificatives KYC (lignes + références fichiers).
            $user->kycDocuments()->delete();

            // Purge des notifications personnelles.
            Notification::where('user_id', $user->id)->delete();

            $placeholderPhone = '+22599'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT);

            $user->forceFill([
                'name' => 'Utilisateur anonymisé #'.$user->id,
                'email' => null,
                'phone' => $placeholderPhone,
                'payment_phone' => null,
                'device_fingerprint' => null,
                'fcm_token' => null,
                'cnmci_number' => null,
                'cnmci_card_url' => null,
                'google_2fa_secret' => null,
                'account_status' => 'suspendu',
                'account_status_reason' => 'Compte anonymisé (RGPD)',
                'blocked_at' => now(),
                'anonymized_at' => now(),
                'anonymized_by' => $actor->id,
            ])->save();

            // Effacement de la position GPS.
            if (config('database.default') === 'sqlite') {
                DB::table('users')->where('id', $user->id)->update(['position' => null]);
            } else {
                DB::statement('UPDATE users SET position = NULL WHERE id = ?', [$user->id]);
            }

            // Révocation de tous les jetons d'accès.
            $user->tokens()->delete();
        });

        $this->audit->log(
            'user.anonymized',
            $user,
            ['reason' => 'RGPD — droit à l\'effacement'],
            subjectLabel: 'Compte #'.$user->id,
            actor: $actor,
        );
    }
}
