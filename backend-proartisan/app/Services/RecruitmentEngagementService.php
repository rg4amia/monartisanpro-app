<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
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

        $totalDays ??= $offer->inclusiveDurationDays();

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

            // Réutilise le séquestre d'accès aux candidatures déjà payé sur
            // l'offre (non encore consommé par un autre engagement en cours).
            if ($offer->applicants_escrow_transaction_id && ! $offer->applicants_escrow_reserved) {
                $engagement->update([
                    'prepaid_transaction_id' => $offer->applicants_escrow_transaction_id,
                    'prepaid_amount' => (int) ($offer->applicantsEscrowTransaction?->montant ?? 0),
                ]);
                $offer->update(['applicants_escrow_reserved' => true]);
            }

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

        $activatedImmediately = false;
        $surplus = 0;

        DB::transaction(function () use ($engagement, &$activatedImmediately, &$surplus) {
            $prepaid = $engagement->prepaid_amount;
            $coveredDays = $prepaid > 0 ? min(intdiv($prepaid, $engagement->daily_rate), $engagement->total_days) : 0;

            for ($day = 1; $day <= $engagement->total_days; $day++) {
                RecruitmentWorkday::create([
                    'engagement_id' => $engagement->id,
                    'day_number' => $day,
                    'montant' => $engagement->daily_rate,
                    'status' => $day <= $coveredDays ? 'pending' : 'awaiting_payment',
                ]);
            }

            $activatedImmediately = $coveredDays >= $engagement->total_days && $prepaid >= $engagement->montant_total;

            $engagement->update([
                'status' => $activatedImmediately ? 'active' : 'pending_payment',
                'accepted_at' => now(),
            ]);

            if ($activatedImmediately) {
                $surplus = $prepaid - $engagement->montant_total;
            }
        });

        if ($activatedImmediately && $surplus > 0) {
            $this->wallet->credit(
                $engagement->recruiter,
                WalletType::WALLET_MO,
                $surplus,
                "Recrutement #{$engagement->id} — trop-perçu du séquestre d'accès aux candidatures",
                [
                    'recruitment_engagement_id' => $engagement->id,
                    'recruitment_offer_id' => $engagement->offer_id,
                    'type' => 'recruitment_offer_escrow_surplus',
                ],
            );

            $this->notifications->send(
                $engagement->recruiter,
                'payment',
                'Trop-perçu remboursé',
                "Le trop-perçu de {$surplus} FCFA sur le séquestre d'accès aux candidatures a été crédité sur votre portefeuille.",
                ['recruitment_engagement_id' => $engagement->id],
            );
        }

        if ($activatedImmediately) {
            $this->notifications->send(
                $engagement->recruiter,
                'recruitment',
                'Engagement accepté',
                "{$engagement->artisan->name} a accepté votre proposition. Le séquestre déjà payé couvre l'intégralité de la mission : elle démarre immédiatement.",
                ['recruitment_engagement_id' => $engagement->id],
            );

            $this->notifications->send(
                $engagement->artisan,
                'payment',
                'Séquestre payé',
                "Le séquestre de votre mission « {$engagement->offer->title} » est déjà réglé. Vous pouvez commencer à travailler.",
                ['recruitment_engagement_id' => $engagement->id],
            );
        } else {
            $this->notifications->send(
                $engagement->recruiter,
                'recruitment',
                'Engagement accepté',
                "{$engagement->artisan->name} a accepté votre proposition. Payez le séquestre pour démarrer la mission.",
                ['recruitment_engagement_id' => $engagement->id],
            );
        }

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

        DB::transaction(function () use ($engagement) {
            $engagement->update(['status' => 'cancelled']);

            // Libère le séquestre d'accès aux candidatures pour qu'il puisse
            // être réutilisé avec un autre candidat de la même offre.
            if ($engagement->prepaid_transaction_id) {
                $engagement->offer->update(['applicants_escrow_reserved' => false]);
            }
        });

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
     * Active le séquestre après confirmation d'un paiement Wave/Orange Money,
     * à la demande du recruteur (appel de l'application). Idempotent : si la
     * confirmation de l'opérateur l'a déjà appliqué, l'appel réussit sans
     * effet supplémentaire.
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

        $this->applyEngagementEscrow($engagement, $transaction);

        return $engagement->fresh();
    }

    /**
     * Applique un paiement confirmé au séquestre d'un engagement, quelle que
     * soit la voie de confirmation (webhook, interrogation de statut,
     * simulateur, appel de l'application — Règle d'or 36).
     *
     * Seules les journées couvertes par CE paiement deviennent validables :
     * leurs identifiants sont figés à l'initiation (`workday_ids`). Une
     * prolongation ajoutée entre-temps attend son propre paiement. Idempotent.
     *
     * @return bool vrai si le paiement vient d'être appliqué
     */
    public function applyEngagementEscrow(RecruitmentEngagement $engagement, Transaction $transaction): bool
    {
        $applied = DB::transaction(function () use ($engagement, $transaction) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

            if (! $transaction->statut->isSuccessful() || ! empty($transaction->metadata['activated'])) {
                return false;
            }

            $awaiting = $engagement->workdays()->where('status', 'awaiting_payment')->orderBy('day_number')->get();
            $paidIds = $transaction->metadata['workday_ids'] ?? null;

            if (is_array($paidIds)) {
                $covered = $awaiting->whereIn('id', $paidIds);
            } else {
                // Paiement initié avant la mémorisation des journées : on ne
                // débloque que ce que son montant couvre, dans l'ordre des jours.
                $budget = (int) $transaction->montant;
                $covered = $awaiting->takeWhile(function ($workday) use (&$budget) {
                    $budget -= $workday->montant;

                    return $budget >= 0;
                });
            }

            $engagement->workdays()->whereIn('id', $covered->pluck('id'))->update(['status' => 'pending']);

            $engagement->update([
                'status' => in_array($engagement->status, ['pending_payment', 'active'], true) ? 'active' : $engagement->status,
            ]);

            $transaction->update(['metadata' => array_merge($transaction->metadata ?? [], ['activated' => true])]);

            return true;
        });

        if ($applied) {
            $this->notifications->send(
                $engagement->artisan,
                'payment',
                'Séquestre payé',
                "Le recruteur a payé le séquestre de votre mission « {$engagement->offer->title} ». Vous pouvez commencer à travailler.",
                ['recruitment_engagement_id' => $engagement->id],
            );
        }

        return $applied;
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

    /**
     * Calcule le montant du séquestre d'accès aux candidatures d'une offre
     * (taux journalier saisi par le recruteur × durée) sans initier de
     * paiement — utilisé pour construire la transaction Wave/Orange Money.
     */
    public function applicantsUnlockAmount(RecruitmentOffer $offer, int $dailyRate, ?int $totalDays = null): array
    {
        if ($dailyRate <= 0) {
            throw ValidationException::withMessages([
                'daily_rate' => ['Le taux journalier doit être positif.'],
            ]);
        }

        $totalDays ??= $offer->inclusiveDurationDays();

        if (! $totalDays || $totalDays <= 0) {
            throw ValidationException::withMessages([
                'total_days' => ["Impossible de déterminer le nombre de jours : précisez-le ou renseignez la période de l'offre."],
            ]);
        }

        return ['amount' => $dailyRate * $totalDays, 'total_days' => $totalDays];
    }

    public function assertRecruiterOwnsOffer(User $recruiter, RecruitmentOffer $offer): void
    {
        if ($offer->creator_id !== $recruiter->id && $recruiter->role !== 'admin') {
            throw ValidationException::withMessages([
                'offer' => ['Cette offre ne vous appartient pas.'],
            ]);
        }
    }

    /**
     * Active le séquestre d'accès aux candidatures à la demande du recruteur.
     * Idempotent : si la confirmation de l'opérateur a déjà débloqué l'offre
     * avec cette même transaction, l'appel réussit.
     */
    public function activateApplicantsUnlock(User $recruiter, RecruitmentOffer $offer, Transaction $transaction): RecruitmentOffer
    {
        $this->assertRecruiterOwnsOffer($recruiter, $offer);

        if ($transaction->type !== 'recruitment_offer_escrow'
            || (int) ($transaction->metadata['recruitment_offer_id'] ?? 0) !== $offer->id) {
            throw ValidationException::withMessages([
                'transaction' => ['Cette transaction ne correspond pas à cette offre.'],
            ]);
        }

        if (! $transaction->statut->isSuccessful()) {
            throw ValidationException::withMessages([
                'transaction' => ['Le paiement du séquestre doit être confirmé avant activation.'],
            ]);
        }

        if ($offer->applicantsUnlocked() && $offer->applicants_escrow_transaction_id !== $transaction->id) {
            throw ValidationException::withMessages([
                'transaction' => ['Les candidatures de cette offre sont déjà consultables.'],
            ]);
        }

        $this->applyApplicantsUnlock($offer, $transaction);

        return $offer->fresh();
    }

    /**
     * Applique un paiement confirmé du séquestre d'accès aux candidatures,
     * quelle que soit la voie de confirmation (Règle d'or 36). Idempotent.
     *
     * @return bool vrai si l'offre vient d'être débloquée
     */
    public function applyApplicantsUnlock(RecruitmentOffer $offer, Transaction $transaction): bool
    {
        return DB::transaction(function () use ($offer, $transaction) {
            $offer = RecruitmentOffer::whereKey($offer->id)->lockForUpdate()->first();

            if (! $transaction->statut->isSuccessful() || $offer->applicantsUnlocked()) {
                return false;
            }

            $offer->update([
                'applicants_escrow_transaction_id' => $transaction->id,
                'applicants_unlocked_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Point d'entrée des confirmations de paiement (PaymentService) pour les
     * séquestres de recrutement ; sans effet sur les autres transactions.
     */
    public function applyConfirmedPayment(Transaction $transaction): void
    {
        if ($transaction->type === 'recruitment_escrow') {
            $engagement = RecruitmentEngagement::find($transaction->metadata['recruitment_engagement_id'] ?? null);
            if ($engagement) {
                $this->applyEngagementEscrow($engagement, $transaction);
            }
        } elseif ($transaction->type === 'recruitment_offer_escrow') {
            $offer = RecruitmentOffer::find($transaction->metadata['recruitment_offer_id'] ?? null);
            if ($offer) {
                $this->applyApplicantsUnlock($offer, $transaction);
            }
        }
    }

    private function assertArtisanOwnsEngagement(User $artisan, RecruitmentEngagement $engagement): void
    {
        if ($engagement->artisan_id !== $artisan->id) {
            throw ValidationException::withMessages([
                'engagement' => ['Cet engagement ne vous appartient pas.'],
            ]);
        }
    }
}
