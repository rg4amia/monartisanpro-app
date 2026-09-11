<?php

namespace App\Services;

use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    /**
     * Seuil de validation ultra-rapide en secondes (2 minutes).
     */
    public const FAST_VALIDATION_THRESHOLD_SECONDS = 120;

    /**
     * Distance maximale admise entre la photo du jalon et le chantier (en mètres).
     */
    public const MAX_SITE_ANOMALY_METERS = 5000;

    /**
     * Score de risque seuil pour déclencher un gel conservatoire automatique des fonds.
     */
    public const PAYMENT_HOLD_RISK_THRESHOLD = 75;

    /**
     * Analyse la soumission d'un jalon par l'artisan (photos, GPS, doublons).
     *
     * @param Jalon $jalon
     * @param array $photos Liste des photos soumises [{"url": "...", "lat": 5.3, "lng": -4.0}]
     * @return FraudAlert|null
     */
    public function analyzeMilestoneSubmission(Jalon $jalon, array $photos = []): ?FraudAlert
    {
        $mission = $jalon->mission;
        if (! $mission) {
            return null;
        }

        $reasons = [];
        $metadata = [
            'jalon_id' => $jalon->id,
            'mission_id' => $mission->id,
            'artisan_id' => $mission->artisan_id,
            'photos_count' => count($photos),
        ];
        $riskScore = 0;
        $type = 'duplicate_proof';
        $severity = 'low';

        // 1. Détection de géolocalisation incohérente avec le chantier
        $siteCoords = $this->getMissionSiteCoords($mission);
        if ($siteCoords && ! empty($photos)) {
            foreach ($photos as $idx => $photo) {
                if (isset($photo['lat'], $photo['lng']) && is_numeric($photo['lat']) && is_numeric($photo['lng'])) {
                    $distance = $this->haversineDistance(
                        (float) $siteCoords['lat'],
                        (float) $siteCoords['lng'],
                        (float) $photo['lat'],
                        (float) $photo['lng']
                    );

                    if ($distance > self::MAX_SITE_ANOMALY_METERS) {
                        $reasons[] = sprintf(
                            "Photo #%d prise à %.1f km du chantier déclaré (seuil max : %.1f km).",
                            $idx + 1,
                            $distance / 1000,
                            self::MAX_SITE_ANOMALY_METERS / 1000
                        );
                        $metadata['distance_to_site_meters'] = round($distance);
                        $metadata['photo_coords'] = ['lat' => (float) $photo['lat'], 'lng' => (float) $photo['lng']];
                        $metadata['site_coords'] = $siteCoords;
                        $type = 'gps_anomaly_site';
                        $severity = 'high';
                        $riskScore = max($riskScore, 75);
                        break;
                    }
                }
            }
        }

        // 2. Détection de photos dupliquées sur d'autres missions
        if (! empty($photos)) {
            $duplicateFound = false;
            foreach ($photos as $photo) {
                $url = is_string($photo) ? $photo : ($photo['url'] ?? null);
                if ($url) {
                    $existing = Jalon::where('id', '!=', $jalon->id)
                        ->where('photos_json', 'LIKE', '%' . basename($url) . '%')
                        ->first();

                    if ($existing) {
                        $reasons[] = "Preuve photo identique détectée sur un autre jalon (#{$existing->id}) de la mission #{$existing->mission_id}.";
                        $metadata['duplicate_photo_url'] = $url;
                        $metadata['duplicate_jalon_id'] = $existing->id;
                        $type = 'duplicate_proof';
                        $severity = 'critical';
                        $riskScore = max($riskScore, 90);
                        $duplicateFound = true;
                        break;
                    }
                }
            }
        }

        if (empty($reasons)) {
            return null;
        }

        return $this->createAlert([
            'mission_id' => $mission->id,
            'user_id' => $mission->artisan_id,
            'target_user_id' => $mission->client_id,
            'type' => $type,
            'severity' => $severity,
            'risk_score' => $riskScore,
            'reasons_json' => $reasons,
            'metadata_json' => $metadata,
        ]);
    }

    /**
     * Analyse la validation d'un jalon par le client (vitesse de validation OTP, collusion).
     *
     * @param Jalon $jalon
     * @param User|null $client
     * @return FraudAlert|null
     */
    public function analyzeMilestoneValidation(Jalon $jalon, ?User $client = null, $submittedAt = null): ?FraudAlert
    {
        $mission = $jalon->mission;
        if (! $mission) {
            return null;
        }

        $submittedAtTime = $submittedAt ?? $jalon->getOriginal('updated_at') ?? $jalon->updated_at ?? now();
        if (is_string($submittedAtTime)) {
            $submittedAtTime = \Carbon\Carbon::parse($submittedAtTime);
        }
        $validatedAt = now();
        $secondsToValidate = abs($validatedAt->getTimestamp() - $submittedAtTime->getTimestamp());

        // Détection de validation ultra-rapide (< 120 secondes)
        if ($secondsToValidate < self::FAST_VALIDATION_THRESHOLD_SECONDS) {
            $reasons = [
                sprintf(
                    "Validation du jalon effectuée en seulement %d secondes après soumission par l'artisan (seuil de vigilance : %d s).",
                    $secondsToValidate,
                    self::FAST_VALIDATION_THRESHOLD_SECONDS
                ),
                "Suspicion de collusion directe ou d'entente préalable pour siphonner le séquestre sans vérification réelle sur le chantier.",
            ];

            // Évaluation du nombre de missions conjointes artisan/client
            $clientUser = $client ?? $mission->client;
            $artisanUser = $mission->artisan;
            $jointMissionsCount = Mission::where('client_id', $mission->client_id)
                ->where('artisan_id', $mission->artisan_id)
                ->count();

            $riskScore = 80;
            if ($jointMissionsCount >= 3) {
                $riskScore = 90;
                $reasons[] = "Ce client et cet artisan ont déjà réalisé {$jointMissionsCount} missions ensemble.";
            }

            return $this->createAlert([
                'mission_id' => $mission->id,
                'user_id' => $mission->client_id,
                'target_user_id' => $mission->artisan_id,
                'type' => 'fast_otp_validation',
                'severity' => 'high',
                'risk_score' => $riskScore,
                'reasons_json' => $reasons,
                'metadata_json' => [
                    'jalon_id' => $jalon->id,
                    'mission_id' => $mission->id,
                    'seconds_to_validate' => $secondsToValidate,
                    'joint_missions_count' => $jointMissionsCount,
                ],
            ]);
        }

        return null;
    }

    /**
     * Analyse le scan et l'encaissement d'un J-Code par un fournisseur.
     *
     * @param JCode $jcode
     * @param User $fournisseur
     * @param float $scanLat
     * @param float $scanLng
     * @param float $distanceMetres
     * @return FraudAlert|null
     */
    public function analyzeJCodeRedemption(
        JCode $jcode,
        User $fournisseur,
        float $scanLat,
        float $scanLng,
        float $distanceMetres
    ): ?FraudAlert {
        $reasons = [];
        $riskScore = 0;
        $type = 'gps_anomaly_jcode';
        $severity = 'medium';

        // 1. Vérification distance GPS boutique (> 100m)
        if ($distanceMetres > 100) {
            $reasons[] = sprintf(
                "Scan J-Code hors périmètre boutique : distance mesurée à %.1f m de la boutique (max autorisé : 100 m).",
                $distanceMetres
            );
            $type = 'gps_anomaly_jcode';
            $severity = 'critical';
            $riskScore = 95;
        }

        // 2. Vérification fréquence de rachat entre le même artisan et ce fournisseur (24h)
        $recentRedemptions = JCode::where('fournisseur_id', $fournisseur->id)
            ->where('artisan_id', $jcode->artisan_id)
            ->where('scanned_at', '>=', now()->subHours(24))
            ->count();

        if ($recentRedemptions >= 3) {
            $reasons[] = sprintf(
                "Fréquence anormale : %d scans de J-Codes effectués entre cet artisan et cette quincaillerie au cours des dernières 24h.",
                $recentRedemptions + 1
            );
            $type = 'collusion_artisan_fournisseur';
            $severity = ($severity === 'critical') ? 'critical' : 'high';
            $riskScore = max($riskScore, 75);
        }

        if (empty($reasons)) {
            return null;
        }

        return $this->createAlert([
            'mission_id' => $jcode->mission_id,
            'user_id' => $fournisseur->id,
            'target_user_id' => $jcode->artisan_id,
            'type' => $type,
            'severity' => $severity,
            'risk_score' => $riskScore,
            'reasons_json' => $reasons,
            'metadata_json' => [
                'jcode_code' => $jcode->code,
                'distance_meters' => round($distanceMetres, 1),
                'scan_coords' => ['lat' => $scanLat, 'lng' => $scanLng],
                'recent_scans_24h' => $recentRedemptions,
            ],
        ]);
    }

    /**
     * Enregistre l'alerte de fraude et déclenche le gel préventif si risque critique.
     */
    public function createAlert(array $params): FraudAlert
    {
        $riskScore = (int) ($params['risk_score'] ?? 50);
        $actionTaken = $params['action_taken'] ?? 'none';

        if ($riskScore >= self::PAYMENT_HOLD_RISK_THRESHOLD && $actionTaken === 'none') {
            $actionTaken = 'payment_hold';
        }

        $alert = FraudAlert::create([
            'reference' => FraudAlert::generateReference(),
            'mission_id' => $params['mission_id'] ?? null,
            'user_id' => $params['user_id'],
            'target_user_id' => $params['target_user_id'] ?? null,
            'type' => $params['type'],
            'severity' => $params['severity'] ?? 'medium',
            'risk_score' => $riskScore,
            'reasons_json' => $params['reasons_json'] ?? [],
            'metadata_json' => $params['metadata_json'] ?? [],
            'statut' => 'ouverte',
            'action_taken' => $actionTaken,
        ]);

        if ($actionTaken === 'payment_hold') {
            Log::warning(sprintf(
                "[FRAUD PAYMENT HOLD] Alerte #%s | Type: %s | Score: %d/100 | Mission #%s",
                $alert->reference,
                $alert->type,
                $alert->risk_score,
                $alert->mission_id ?? 'N/A'
            ));
        }

        return $alert;
    }

    /**
     * Calcule la distance orthodromique entre deux points en mètres (formule de Haversine).
     */
    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Rayon en mètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Extrait les coordonnées GPS estimées du chantier depuis la mission ou le client.
     */
    private function getMissionSiteCoords(Mission $mission): ?array
    {
        $lat = $mission->client_latitude ?? $mission->latitude ?? null;
        $lng = $mission->client_longitude ?? $mission->longitude ?? null;

        if ($lat !== null && $lng !== null) {
            return [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        $client = $mission->client;
        if ($client) {
            return $client->getPositionCoords();
        }

        return null;
    }

    /**
     * Enregistre une alerte anti-fraude liée à une incohérence visuelle détectée par Gemini Vision.
     */
    public function recordVisionAnomaly(Jalon $jalon, array $visionAnalysis): ?FraudAlert
    {
        $mission = $jalon->mission;
        if (! $mission) {
            return null;
        }

        $conformityScore = (int) ($visionAnalysis['conformity_score'] ?? 0);
        $riskScore = max(60, min(100, 100 - $conformityScore));
        $severity = $riskScore >= 80 ? 'critical' : 'high';

        $reasons = [];
        $reasons[] = sprintf(
            "Incohérence visuelle détectée par Gemini Vision sur le jalon #%d : score de conformité de %d/100 (seuil de conformité : 40).",
            $jalon->ordre,
            $conformityScore
        );

        if (! empty($visionAnalysis['anomalies'])) {
            $reasons[] = 'Anomalies relevées : ' . implode(', ', (array) $visionAnalysis['anomalies']);
        }

        if (! empty($visionAnalysis['summary'])) {
            $reasons[] = 'Analyse technique : ' . $visionAnalysis['summary'];
        }

        $metadata = [
            'jalon_id'          => $jalon->id,
            'jalon_ordre'       => $jalon->ordre,
            'mission_id'        => $mission->id,
            'artisan_id'        => $mission->artisan_id,
            'client_id'         => $mission->client_id,
            'conformity_score'  => $conformityScore,
            'detected_elements' => $visionAnalysis['detected_elements'] ?? [],
            'anomalies'         => $visionAnalysis['anomalies'] ?? [],
            'summary'           => $visionAnalysis['summary'] ?? null,
            'confidence'        => $visionAnalysis['confidence'] ?? 'medium',
        ];

        return $this->createAlert([
            'mission_id'     => $mission->id,
            'user_id'        => $mission->artisan_id,
            'target_user_id' => $mission->client_id,
            'type'           => 'vision_mismatch',
            'severity'       => $severity,
            'risk_score'     => $riskScore,
            'reasons_json'   => $reasons,
            'metadata_json'  => $metadata,
        ]);
    }
}
