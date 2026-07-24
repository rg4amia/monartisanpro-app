<?php

namespace App\Services;

use App\Models\User;
use App\Models\CreditApplication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicroCreditService
{
    public function __construct(
        private ScoreService $scoreService,
        private NotificationService $notificationService
    ) {}

    /**
     * Vérifie l'éligibilité et retourne le montant max accordable.
     */
    public function checkEligibility(User $artisan): array
    {
        if (! $this->scoreService->isEligibleCredit($artisan)) {
            return [
                'eligible' => false,
                'reason' => 'Score ProsArtisan < 70. Améliorez votre score en complétant des missions.',
                'current_score' => $artisan->score_prosartisan,
                'required_score' => 70,
            ];
        }

        // Calcul montant max basé sur score et historique
        $scoreDetail = $this->scoreService->getScoreDetail($artisan);
        $maxAmount = $this->calculateMaxCredit($artisan, $scoreDetail);

        return [
            'eligible' => true,
            'max_amount' => $maxAmount,
            'score_prosartisan' => $artisan->score_prosartisan,
            'total_evaluations' => $scoreDetail['total_evaluations'],
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
            'score_prosartisan_at_application' => $artisan->score_prosartisan,
            'status' => 'en_attente',
        ]);

        // Appel API microfinance partenaire (simulé si non configuré)
        $baseUrl = config('services.microfinance.base_url');
        $apiKey = config('services.microfinance.api_key');

        if ($baseUrl && $apiKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->post($baseUrl . '/applications', [
                    'artisan_id' => $artisan->id,
                    'artisan_phone' => $artisan->phone,
                    'artisan_name' => $artisan->name,
                    'amount' => $amount,
                    'score_prosartisan' => $artisan->score_prosartisan,
                    'score_breakdown' => $this->scoreService->getScoreDetail($artisan),
                ]);

                if ($response->successful()) {
                    $application->update([
                        'external_reference' => $response->json()['application_id'] ?? null,
                        'status' => 'approuve',
                        'approved_at' => now(),
                    ]);

                    Log::info('Demande micro-crédit approuvée', [
                        'application_id' => $application->id,
                        'artisan_id' => $artisan->id,
                        'amount' => $amount,
                    ]);
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
            // En simulation, on approuve automatiquement pour le test
            $application->update([
                'status' => 'approuve',
                'approved_at' => now(),
            ]);
        }

        $this->notificationService->send(
            $artisan,
            'credit',
            'Demande de crédit soumise',
            "Votre demande de crédit de {$amount} FCFA a été soumise. Déblocage prévu sous 2h.",
            ['application_id' => $application->id]
        );

        return $application;
    }

    private function calculateMaxCredit(User $artisan, array $scoreDetail): int
    {
        // Formule : Base 50 000 FCFA + (score - 70) * 5 000 FCFA par point
        $base = 50000;
        $perPoint = 5000;
        $scoreAboveThreshold = max(0, $artisan->score_prosartisan - 70);

        return $base + ($scoreAboveThreshold * $perPoint);
    }
}
