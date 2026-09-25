<?php

namespace App\Services;

use App\Models\CreditApplication;
use App\Models\Jalon;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicroCreditService
{
    public function __construct(
        private ScoreService $scoreService,
        private NotificationService $notificationService
    ) {}

    /**
     * Retourne le crédit actif ou en cours de l'artisan.
     */
    public function getActiveCredit(User $artisan): ?CreditApplication
    {
        return CreditApplication::where('user_id', $artisan->id)
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Vérifie l'éligibilité et retourne le montant max accordable.
     */
    public function checkEligibility(User $artisan): array
    {
        // 1. KYC actif obligatoire
        if (! $artisan->isKycActif()) {
            return [
                'eligible' => false,
                'reason' => 'Votre dossier KYC doit être validé pour accéder au micro-crédit d\'urgence.',
                'current_score' => (int) $artisan->score_prosartisan,
                'required_score' => (int) config('prosartisan.score_prosartisan.credit_threshold', 700),
                'has_active_credit' => false,
            ];
        }

        // 2. Vérification d'un crédit actif existant
        $activeCredit = $this->getActiveCredit($artisan);
        if ($activeCredit) {
            return [
                'eligible' => false,
                'reason' => "Vous avez déjà un micro-crédit en cours (solde restant : {$activeCredit->remaining_amount} FCFA). Soldez-le avant toute nouvelle demande.",
                'current_score' => (int) $artisan->score_prosartisan,
                'required_score' => (int) config('prosartisan.score_prosartisan.credit_threshold', 700),
                'has_active_credit' => true,
                'active_credit' => [
                    'id' => $activeCredit->id,
                    'amount' => $activeCredit->amount,
                    'repaid_amount' => $activeCredit->repaid_amount ?? 0,
                    'remaining_amount' => $activeCredit->remaining_amount,
                    'status' => $activeCredit->status,
                    'approved_at' => $activeCredit->approved_at?->toIso8601String(),
                ],
            ];
        }

        $threshold = (int) config('prosartisan.score_prosartisan.credit_threshold', 700);

        // Le score est recalculé depuis le ledger AVANT toute décision : éligibilité
        // et plafond reposent sur la même valeur. Juger l'éligibilité sur la colonne
        // stockée (potentiellement obsolète) puis plafonner sur le score recalculé
        // accordait un crédit à un artisan dont le score réel est sous le seuil.
        $scoreDetail = $this->scoreService->getScoreDetail($artisan);
        $score = (int) $scoreDetail['score_prosartisan'];

        if ($score < $threshold) {
            return [
                'eligible' => false,
                'reason' => "Score ProsArtisan < {$threshold}. Améliorez votre score en complétant des missions.",
                'current_score' => $score,
                'required_score' => $threshold,
                'has_active_credit' => false,
            ];
        }

        return [
            'eligible' => true,
            'max_amount' => $this->calculateMaxCredit($score, $threshold),
            'score_prosartisan' => $score,
            'current_score' => $score,
            'required_score' => $threshold,
            'total_evaluations' => $scoreDetail['total_evaluations'],
            'has_active_credit' => false,
        ];
    }

    /**
     * Soumet une demande de crédit à la microfinance partenaire.
     */
    public function applyForCredit(User $artisan, int $amount): CreditApplication
    {
        $eligibility = $this->checkEligibility($artisan);

        if (! $eligibility['eligible']) {
            throw new \Exception($eligibility['reason']);
        }

        if ($amount > $eligibility['max_amount']) {
            throw new \Exception("Montant demandé ({$amount} FCFA) supérieur au maximum autorisé ({$eligibility['max_amount']} FCFA).");
        }

        // Créer la demande en base
        $application = CreditApplication::create([
            'user_id' => $artisan->id,
            'amount' => $amount,
            'repaid_amount' => 0,
            'score_prosartisan_at_application' => $eligibility['score_prosartisan'],
            'status' => 'en_attente',
        ]);

        // Appel API microfinance partenaire (simulé si non configuré)
        $baseUrl = config('services.microfinance.base_url');
        $apiKey = config('services.microfinance.api_key');

        $approved = false;
        $externalRef = null;

        if ($baseUrl && $apiKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])->post($baseUrl.'/applications', [
                    'artisan_id' => $artisan->id,
                    'artisan_phone' => $artisan->phone,
                    'artisan_name' => $artisan->name,
                    'amount' => $amount,
                    'score_prosartisan' => $artisan->score_prosartisan,
                    'score_breakdown' => $this->scoreService->getScoreDetail($artisan),
                ]);

                if ($response->successful()) {
                    $approved = true;
                    $externalRef = $response->json()['application_id'] ?? null;
                }
            } catch (\Exception $e) {
                Log::error('MicroCreditService: Erreur appel API microfinance', [
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('MicroCreditService: Mode simulation (pas d\'API microfinance configurée)', [
                'artisan_id' => $artisan->id,
                'amount' => $amount,
            ]);
            $approved = true;
            $externalRef = 'SIMUL-CREDIT-'.$application->id;
        }

        if ($approved) {
            $application->update([
                'external_reference' => $externalRef,
                'status' => 'debourse',
                'approved_at' => now(),
                'disbursed_at' => now(),
            ]);

            // Tracement de la transaction financière officielle (type: credit)
            $provider = $artisan->preferred_payment_provider ?? 'wave';
            Transaction::create([
                'user_id' => $artisan->id,
                'type' => 'credit',
                'montant' => $amount,
                'wallet_source' => 'microfinance_partner',
                'wallet_dest' => 'artisan_mobile_money_'.$artisan->id,
                'provider' => $provider,
                'statut' => 'confirme',
                'reference_externe' => $externalRef,
            ]);

            Log::info('Demande micro-crédit approuvée et déboursée', [
                'application_id' => $application->id,
                'artisan_id' => $artisan->id,
                'amount' => $amount,
            ]);
        }

        $this->notificationService->send(
            $artisan,
            'credit',
            'Demande de crédit soumise',
            "Votre demande de crédit de {$amount} FCFA a été approuvée et débloquée sous 2h sur votre compte Mobile Money.",
            ['application_id' => $application->id]
        );

        return $application;
    }

    /**
     * Amortit automatiquement le micro-crédit actif lors de la libération d'un jalon de mission.
     * Retourne le montant prélevé pour remboursement (0 si pas de crédit ou jalon non amorti).
     */
    public function repayFromJalon(Jalon $jalon, int $gainNetArtisan): int
    {
        $artisan = $jalon->mission->artisan;
        $activeCredit = $this->getActiveCredit($artisan);

        if (! $activeCredit || $activeCredit->remaining_amount <= 0) {
            return 0;
        }

        // Taux d'amortissement par défaut : 20% du montant net du jalon, plafonné au solde restant
        $repaymentRate = (float) config('prosartisan.score_prosartisan.credit_repayment_rate', 0.20);
        $potentialRepayment = (int) round($gainNetArtisan * $repaymentRate);
        $repaymentAmount = min($activeCredit->remaining_amount, $potentialRepayment);

        if ($repaymentAmount <= 0) {
            return 0;
        }

        $newRepaidAmount = ($activeCredit->repaid_amount ?? 0) + $repaymentAmount;
        $isFullyPaid = $newRepaidAmount >= $activeCredit->amount;

        $activeCredit->update([
            'repaid_amount' => $newRepaidAmount,
            'status' => $isFullyPaid ? 'rembourse' : $activeCredit->status,
            'repaid_at' => $isFullyPaid ? now() : null,
        ]);

        // Tracement transaction de remboursement
        Transaction::create([
            'mission_id' => $jalon->mission_id,
            'user_id' => $artisan->id,
            'type' => 'remboursement',
            'montant' => $repaymentAmount,
            'wallet_source' => 'artisan_mo_jalon_'.$jalon->id,
            'wallet_dest' => 'microfinance_partner',
            'provider' => $this->resolveMissionProvider($jalon->mission, $artisan),
            'statut' => 'confirme',
            'reference_externe' => 'REPAY-JALON-'.$jalon->id,
        ]);

        $this->notificationService->send(
            $artisan,
            'credit',
            'Amortissement micro-crédit',
            "Une retenue de {$repaymentAmount} FCFA a été prélevée sur votre jalon #{$jalon->ordre} au titre du remboursement de votre micro-crédit. Solde restant : {$activeCredit->remaining_amount} FCFA.",
            ['credit_application_id' => $activeCredit->id, 'jalon_id' => $jalon->id]
        );

        Log::info("[MicroCredit] Amortissement jalon #{$jalon->id} : {$repaymentAmount} FCFA prélevés (reste: {$activeCredit->remaining_amount} FCFA)");

        return $repaymentAmount;
    }

    /**
     * Remboursement volontaire direct par l'artisan via Mobile Money.
     */
    public function repayCredit(User $artisan, int $amount, string $provider = 'wave'): array
    {
        $activeCredit = $this->getActiveCredit($artisan);

        if (! $activeCredit) {
            throw new \Exception('Vous n\'avez aucun micro-crédit actif à rembourser.');
        }

        if ($amount <= 0) {
            throw new \Exception('Le montant de remboursement doit être supérieur à 0 FCFA.');
        }

        $actualRepayment = min($activeCredit->remaining_amount, $amount);
        $newRepaidAmount = ($activeCredit->repaid_amount ?? 0) + $actualRepayment;
        $isFullyPaid = $newRepaidAmount >= $activeCredit->amount;

        $activeCredit->update([
            'repaid_amount' => $newRepaidAmount,
            'status' => $isFullyPaid ? 'rembourse' : $activeCredit->status,
            'repaid_at' => $isFullyPaid ? now() : null,
        ]);

        Transaction::create([
            'user_id' => $artisan->id,
            'type' => 'remboursement',
            'montant' => $actualRepayment,
            'wallet_source' => 'artisan_mobile_money_'.$artisan->id,
            'wallet_dest' => 'microfinance_partner',
            'provider' => $provider,
            'statut' => 'confirme',
            'reference_externe' => 'VOLUNTARY-REPAY-'.$activeCredit->id.'-'.time(),
        ]);

        $this->notificationService->send(
            $artisan,
            'credit',
            'Remboursement micro-crédit validé',
            "Votre remboursement de {$actualRepayment} FCFA a été enregistré avec succès. Solde restant dû : {$activeCredit->remaining_amount} FCFA.",
            ['credit_application_id' => $activeCredit->id]
        );

        return [
            'repaid_amount' => $actualRepayment,
            'total_repaid' => $newRepaidAmount,
            'remaining_amount' => $activeCredit->remaining_amount,
            'is_fully_repaid' => $isFullyPaid,
            'status' => $activeCredit->status,
        ];
    }

    private function calculateMaxCredit(int $score, int $threshold): int
    {
        // Formule : Base 50 000 FCFA + (score - seuil) * 1 500 FCFA par point au-dessus du seuil.
        // Sur l'échelle 0–1000 : seuil 700 → score 1000 plafonne le crédit à 50 000 + 300 × 1 500 = 500 000 FCFA.
        $base = 50000;
        $perPoint = 1500;

        return $base + (max(0, $score - $threshold) * $perPoint);
    }

    private function resolveMissionProvider($mission, User $artisan): string
    {
        return $artisan->preferred_payment_provider ?? 'wave';
    }
}
