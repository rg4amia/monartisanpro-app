<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\CompletedState;
use InvalidArgumentException;

class SolvencyPassportService
{
    public function __construct(private ScoreService $scoreService) {}

    /**
     * Génère un passeport de solvabilité bancaire certifié (Chantier 9C).
     * RÈGLE 77 : le score est recalculé depuis le ledger avant toute décision.
     */
    public function generatePassport(User $artisan): array
    {
        if ($artisan->role !== 'artisan') {
            throw new InvalidArgumentException("Seul un artisan peut disposer d'un passeport de solvabilité.");
        }

        // 1. Recalcul certifié du Score ProsArtisan depuis le ledger (Règles 9 & 77)
        $scoreDetail = $this->scoreService->getScoreDetail($artisan);
        $score = (int) ($scoreDetail['score_prosartisan'] ?? $scoreDetail['score'] ?? 0);

        // 2. Métriques d'activité et d'historique
        $completedMissionsCount = Mission::where('artisan_id', $artisan->id)
            ->where('status', CompletedState::class)
            ->count();

        $totalRevenue = (int) Mission::where('artisan_id', $artisan->id)
            ->where('status', CompletedState::class)
            ->sum('montant_total');

        $averageRating = (float) (Evaluation::where('evalue_id', $artisan->id)->avg('note') ?? 0.0);
        $evaluationsCount = Evaluation::where('evalue_id', $artisan->id)->count();

        $disputedMissionsCount = Litige::whereHas('mission', function ($q) use ($artisan) {
            $q->where('artisan_id', $artisan->id);
        })->count();

        $totalMissions = Mission::where('artisan_id', $artisan->id)->count();
        $disputeRate = $totalMissions > 0 ? round(($disputedMissionsCount / $totalMissions) * 100, 1) : 0.0;

        // 3. Palier et éligibilité bancaire
        $solvencyTier = match (true) {
            $score >= 800 => 'Excellence (Éligibilité Maximale)',
            $score >= 700 => 'Solide (Éligible Financement Immédiat)',
            $score >= 500 => 'Émergent (Accompagnement Recommandé)',
            default => 'En amorçage (Non éligible au crédit)',
        };

        $creditCeiling = $score >= 700
            ? 50000 + (($score - 700) * 1500)
            : 0;

        // 4. Jeton cryptographique d'authentification HMAC (anti-falsification)
        $payloadToSign = "passport:{$artisan->id}:{$score}:{$artisan->phone}";
        $signature = hash_hmac('sha256', $payloadToSign, config('app.key') ?? 'prosartisan-secret');

        $token = base64_encode(json_encode([
            'artisan_id' => $artisan->id,
            'score' => $score,
            'sig' => $signature,
            'issued_at' => now()->timestamp,
        ]));

        $verificationUrl = url("/api/v1/solvency-passports/verify?token=".urlencode($token));

        return [
            'artisan' => [
                'id' => $artisan->id,
                'name' => $artisan->name,
                'phone' => $artisan->phone,
                'kyc_status' => $artisan->kyc_status,
                'score_prosartisan' => $score,
                'score_breakdown' => $scoreDetail['components'] ?? [],
            ],
            'performance' => [
                'completed_missions_count' => $completedMissionsCount,
                'total_revenue_fcfa' => $totalRevenue,
                'average_rating' => round($averageRating, 1),
                'evaluations_count' => $evaluationsCount,
                'dispute_rate_percent' => $disputeRate,
            ],
            'banking' => [
                'solvency_tier' => $solvencyTier,
                'credit_eligible' => $score >= 700,
                'credit_ceiling_fcfa' => $creditCeiling,
                'partner_institutions' => ['Advans Côte d\'Ivoire', 'Baobab CI', 'Cofina'],
            ],
            'certification' => [
                'token' => $token,
                'verification_url' => $verificationUrl,
                'issued_at' => now()->toIso8601String(),
                'issuer' => 'ProsArtisan Trust & Credit Network',
            ],
        ];
    }

    /**
     * Vérifie l'authenticité d'un passeport de solvabilité scanné via QR code.
     */
    public function verifyPassportToken(string $token): ?array
    {
        try {
            $data = json_decode(base64_decode($token), true);
            if (! is_array($data) || ! isset($data['artisan_id'], $data['score'], $data['sig'])) {
                return null;
            }

            $artisan = User::find($data['artisan_id']);
            if (! $artisan) {
                return null;
            }

            $payloadToSign = "passport:{$artisan->id}:{$data['score']}:{$artisan->phone}";
            $expectedSignature = hash_hmac('sha256', $payloadToSign, config('app.key') ?? 'prosartisan-secret');

            if (! hash_equals($expectedSignature, $data['sig'])) {
                return null;
            }

            // Génère les données temps réel à jour de l'artisan
            return $this->generatePassport($artisan);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Calcule la cotisation de la Micro-Assurance « Garantie Chantier Sérénité » (1.5%).
     */
    public function calculateGuaranteeOption(int $montantDevis): array
    {
        $taux = 0.015; // 1.5%
        $cotisation = max(2500, (int) round($montantDevis * $taux));

        return [
            'option_name' => 'Garantie Chantier Sérénité',
            'montant_devis_fcfa' => $montantDevis,
            'rate_percent' => 1.5,
            'premium_fcfa' => $cotisation,
            'coverage_fcfa' => $montantDevis,
            'covered_risks' => [
                'abandon_chantier' => 'Indemnisation expresse en cas de défection sous 48h',
                'malfacon_majeure' => 'Prise en charge de l intervention d un second artisan qualifié',
                'retard_critique' => 'Arbitrage et assistance prioritaire par un Référent de zone',
            ],
        ];
    }
}
