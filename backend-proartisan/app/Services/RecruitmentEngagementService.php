<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentWorkday;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Engagement de recrutement — une candidature confirmée (« Retenu ») donne
 * lieu à un contrat journalier séquestré : l'artisan accepte, le recruteur
 * paie l'intégralité sous séquestre (Wave/Orange Money), et chaque jour
 * travaillé est libéré individuellement à l'artisan (net de la commission
 * ProsArtisan) après validation du client. Le contrat peut être prolongé :
 * le recruteur doit alors compléter le séquestre des jours supplémentaires
 * avant qu'ils ne soient validables.
 */
class RecruitmentEngagementService
{
    public function __construct(
        private WalletService $wallet,
        private NotificationService $notifications,
    ) {}

    /**
     * Le recruteur confirme un candidat et propose un engagement journalier.
     */
    public function createEngagement(
        User $recruiter,
        RecruitmentApplication $application,
        int $dailyRate,
        ?int $totalDays = null,
    ): RecruitmentEngagement {
        $offer = $application->offer;

        if ($offer->creator_id !== $recruiter->id && $recruiter->role !== 'admin') {
            throw ValidationException::withMessages([
                'application' => ["Cette candidature n'appartient pas à l'une de vos offres."],
            ]);
        }

        if ($application->status === 'rejected') {
            throw ValidationException::withMessages([
                'application' => ['Cette candidature a été rejetée.'],
            ]);
        }

        if ($application->engagement()->exists()) {
            throw ValidationException::withMessages([
                'application' => ['Un engagement existe déjà pour cette candidature.'],
            ]);
        }

        if ($dailyRate <= 0) {
            throw ValidationException::withMessages([
                'daily_rate' => ['Le taux journalier doit être positif.'],
            ]);
        }

        $totalDays ??= $this->daysBetween($offer->date_debut, $offer->deadline_at);

        if (! $totalDays || $totalDays <= 0) {
            throw ValidationException::withMessages([
                'total_days' => ["Impossible de déterminer le nombre de jours : précisez-le ou renseignez la période de l'offre."],
            ]);
        }

        $commissionRate = (float) Setting::getValueByKey('commission_recruitment', 0.10);

        $engagement = DB::transaction(function () use ($application, $offer, $recruiter, $dailyRate, $totalDays, $commissionRate) {
            $engagement = RecruitmentEngagement::create([
                'offer_id' => $offer->id,
                'application_id' => $application->id,
                'artisan_id' => $application->artisan_id,
                'recruiter_id' => $recruiter->id,
                'daily_rate' => $dailyRate,
                'total_days' => $totalDays,
                'montant_total' => $dailyRate * $totalDays,
                'commission_rate' => $commissionRate,
                'status' => 'pending_artisan_acceptance',
            ]);

            $application->update(['status' => 'confirmed']);

            return $engagement;
        });

        $this->notifications->send(
            $engagement->artisan,
            'recruitment',
            'Proposition de mission',
            "{$recruiter->name} vous propose un engagement sur « {$offer->title} » — {$dailyRate} FCFA/jour sur {$totalDays} jour(s). Acceptez-le pour démarrer.",
            ['recruitment_engagement_id' => $engagement->id],
        );

        return $engagement;
    }

    public function acceptEngagement(User $artisan, RecruitmentEngagement $engagement): RecruitmentEngagement
    {
        $this->assertArtisanOwnsEngagement($artisan, $engagement);

        if ($engagement->status !== 'pending_artisan_acceptance') {
            throw ValidationException::withMessages([
                'engagement' => ['Cet engagement ne peut plus être accepté.'],
            ]);
        }

        DB::transaction(function () use ($engagement) {
            for ($day = 1; $day <= $engagement->total_days; $day++) {
                RecruitmentWorkday::create([
                    'engagement_id' => $engagement->id,
                    'day_number' => $day,
                    'montant' => $engagement->daily_rate,
                    'status' => 'awaiting_payment',
                ]);
            }

            $engagement->update([
                'status' => 'pending_payment',
                'accepted_at' => now(),
            ]);
        });

        $this->notifications->send(
            $engagement->recruiter,
            'recruitment',
            'Engagement accepté',
            "{$engagement->artisan->name} a accepté votre proposition. Payez le séquestre pour démarrer la mission.",
            ['recruitment_engagement_id' => $engagement->id],
        );

        return $engagement->fresh();
    }

    public function declineEngagement(User $artisan, RecruitmentEngagement $engagement): RecruitmentEngagement
    {
        $this->assertArtisanOwnsEngagement($artisan, $engagement);

        if ($engagement->status !== 'pending_artisan_acceptance') {
            throw ValidationException::withMessages([
                'engagement' => ['Cet engagement ne peut plus être refusé.'],
            ]);
        }

        $engagement->update(['status' => 'cancelled']);

        $this->notifications->send(
            $engagement->recruiter,
            'recruitment',
            'Engagement refusé',
            "{$engagement->artisan->name} a refusé la proposition d'engagement.",
            ['recruitment_engagement_id' => $engagement->id],
        );

        return $engagement;
    }

    /**
     * Prépare (sans initier le paiement) le montant à séquestrer : la somme
     * des jours pas encore payés (initiaux ou ajoutés par prolongation).
     */
    public function unpaidAmount(RecruitmentEngagement $engagement): int
    {
        return (int) $engagement->workdays()->where('status', 'awaiting_payment')->sum('montant');
    }

    public function assertRecruiterOwnsEngagement(User $recruiter, RecruitmentEngagement $engagement): void
    {
        if ($engagement->recruiter_id !== $recruiter->id && $recruiter->role !== 'admin') {
            throw ValidationException::withMessages([
                'engagement' => ["Cet engagement n'appartient pas à l'un de vos recrutements."],
            ]);
        }
    }

    /**
     * Active le séquestre après confirmation d'un paiement Wave/Orange Money :
     * les jours en attente de paiement deviennent validables par le client.
     */
    public function activateEscrow(User $recruiter, RecruitmentEngagement $engagement, Transaction $transaction): RecruitmentEngagement
    {
        $this->assertRecruiterOwnsEngagement($recruiter, $engagement);

        if ($transaction->type !== 'recruitment_escrow'
            || (int) ($transaction->metadata['recruitment_engagement_id'] ?? 0) !== $engagement->id) {
            throw ValidationException::withMessages([
                'transaction' => ['Cette transaction ne correspond pas à cet engagement.'],
            ]);
        }

        if (! $transaction->statut->isSuccessful()) {
            throw ValidationException::withMessages([
                'transaction' => ['Le paiement du séquestre doit être confirmé avant activation.'],
            ]);
        }

        if (! empty($transaction->metadata['activated'])) {
            throw ValidationException::withMessages([
                'transaction' => ['Ce paiement a déjà été appliqué.'],
            ]);
        }

        DB::transaction(function () use ($engagement, $transaction) {
            $engagement->workdays()->where('status', 'awaiting_payment')->update(['status' => 'pending']);

            $engagement->update([
                'status' => in_array($engagement->status, ['pending_payment', 'active'], true) ? 'active' : $engagement->status,
            ]);

            $transaction->update(['metadata' => array_merge($transaction->metadata ?? [], ['activated' => true])]);
        });

        $this->notifications->send(
            $engagement->artisan,
            'payment',
            'Séquestre payé',
            "Le recruteur a payé le séquestre de votre mission « {$engagement->offer->title} ». Vous pouvez commencer à travailler.",
            ['recruitment_engagement_id' => $engagement->id],
        );

        return $engagement->fresh();
    }

    /**
     * Le client valide qu'une journée a été effectivement travaillée : le
     * montant du jour est libéré à l'artisan, net de la commission ProsArtisan.
     */
    public function validateWorkday(User $recruiter, RecruitmentWorkday $workday): RecruitmentWorkday
    {
        $engagement = $workday->engagement;
        $this->assertRecruiterOwnsEngagement($recruiter, $engagement);

        if ($workday->status !== 'pending') {
            throw ValidationException::withMessages([
                'workday' => ["Cette journée n'est pas en attente de validation."],
            ]);
        }

        $completed = false;
        $net = 0;

        DB::transaction(function () use ($workday, $engagement, &$completed, &$net) {
            $commission = (int) round($workday->montant * (float) $engagement->commission_rate);
            $net = $workday->montant - $commission;

            $this->wallet->credit(
                $engagement->artisan,
                WalletType::WALLET_MO,
                $net,
                "Recrutement #{$engagement->id} — jour {$workday->day_number} validé",
                ['recruitment_engagement_id' => $engagement->id, 'recruitment_workday_id' => $workday->id],
            );

            $this->wallet->creditPlatformFinancialAccount(
                $commission,
                "Commission recrutement #{$engagement->id} — jour {$workday->day_number}",
                ['recruitment_engagement_id' => $engagement->id, 'recruitment_workday_id' => $workday->id],
            );

            $workday->update(['status' => 'validated', 'validated_at' => now()]);

            $remaining = $engagement->workdays()->whereIn('status', ['awaiting_payment', 'pending'])->exists();
            if (! $remaining) {
                $engagement->update(['status' => 'completed']);
                $completed = true;

                $offer = $engagement->offer;
                if ($offer->status === 'active') {
                    $offer->update(['status' => 'filled']);
                }
            }
        });

        $this->notifications->send(
            $engagement->artisan,
            'payment',
            $completed ? 'Mission terminée — dernier paiement reçu' : 'Journée payée',
            "Le jour {$workday->day_number} a été validé : {$net} FCFA versés sur votre portefeuille."
                .($completed ? ' Cette mission est maintenant terminée.' : ''),
            ['recruitment_engagement_id' => $engagement->id, 'recruitment_workday_id' => $workday->id],
        );

        return $workday->fresh();
    }

    /**
     * Prolonge le contrat de jours supplémentaires. Le recruteur devra
     * impérativement compléter le séquestre (nouveau paiement) avant que ces
     * jours ne deviennent validables.
     */
    public function extendEngagement(User $recruiter, RecruitmentEngagement $engagement, int $additionalDays): RecruitmentEngagement
    {
        $this->assertRecruiterOwnsEngagement($recruiter, $engagement);

        if (! in_array($engagement->status, ['pending_payment', 'active'], true)) {
            throw ValidationException::withMessages([
                'engagement' => ["Seul un engagement accepté par l'artisan peut être prolongé."],
            ]);
        }

        if ($additionalDays <= 0) {
            throw ValidationException::withMessages([
                'additional_days' => ['Le nombre de jours supplémentaires doit être positif.'],
            ]);
        }

        DB::transaction(function () use ($engagement, $additionalDays) {
            $lastDay = (int) $engagement->workdays()->max('day_number');

            for ($i = 1; $i <= $additionalDays; $i++) {
                RecruitmentWorkday::create([
                    'engagement_id' => $engagement->id,
                    'day_number' => $lastDay + $i,
                    'montant' => $engagement->daily_rate,
                    'status' => 'awaiting_payment',
                ]);
            }

            $engagement->update([
                'total_days' => $engagement->total_days + $additionalDays,
                'montant_total' => $engagement->montant_total + ($additionalDays * $engagement->daily_rate),
            ]);
        });

        $this->notifications->send(
            $engagement->artisan,
            'recruitment',
            'Contrat prolongé',
            "Le recruteur a ajouté {$additionalDays} jour(s) à votre mission « {$engagement->offer->title} ». Le paiement du complément est en attente.",
            ['recruitment_engagement_id' => $engagement->id],
        );

        return $engagement->fresh();
    }

    private function assertArtisanOwnsEngagement(User $artisan, RecruitmentEngagement $engagement): void
    {
        if ($engagement->artisan_id !== $artisan->id) {
            throw ValidationException::withMessages([
                'engagement' => ['Cet engagement ne vous appartient pas.'],
            ]);
        }
    }

    private function daysBetween(?string $start, ?string $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        $days = (strtotime($end) - strtotime($start)) / 86400;

        return $days >= 0 ? ((int) $days) + 1 : null;
    }
}
