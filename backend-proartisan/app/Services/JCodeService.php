<?php

namespace App\Services;

use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JCodeService
{
    public function __construct(
        private GeoService $geoService,
        private WalletService $walletService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Génère un J-Code pour l'achat de matériaux.
     * Format : PA-XXXX (4 caractères alphanumériques majuscules).
     */
    public function generate(Mission $mission, User $artisan, int $montant): JCode
    {
        $code = $this->generateUniqueCode();

        return JCode::create([
            'mission_id'  => $mission->id,
            'artisan_id'  => $artisan->id,
            'code'        => $code,
            'ussd_code'   => '*555*' . str_replace('PA-', '', $code) . '#',
            'qr_url'      => null, // Généré à la demande (URL /api/v1/jcodes/{code}/qr)
            'montant'     => $montant,
            'statut'      => 'actif',
            'expires_at'  => now()->addHours(config('prosartisan.jcode.ttl_hours', 48)),
        ]);
    }

    /**
     * Valide un J-Code lors du scan par le fournisseur.
     * RÈGLE CRITIQUE : distance > 100 m → blocage + alerte admin.
     */
    public function scan(JCode $jcode, User $fournisseur, float $lat, float $lng): array
    {
        if (! $jcode->isActif()) {
            throw ValidationException::withMessages([
                'code' => ['Ce J-Code est expiré ou déjà utilisé.'],
            ]);
        }

        // Vérification GPS obligatoire
        $gpsCheck = $this->geoService->validateJCodeGps($fournisseur->id, $lat, $lng);

        if (! $gpsCheck['valid']) {
            // RÈGLE : blocage + alerte automatique admin
            Log::warning("[GPS FRAUD ALERT] J-Code {$jcode->code} | Fournisseur #{$fournisseur->id} | Distance: {$gpsCheck['distance']} m (max: {$gpsCheck['max']} m)");

            // En production : notifier l'admin immédiatement
            $this->notificationService->sendAdmin(
                'alert',
                'Tentative de fraude J-Code',
                "J-Code {$jcode->code} scanné à {$gpsCheck['distance']} m de la boutique (max {$gpsCheck['max']} m).",
                ['jcode_id' => $jcode->id, 'fournisseur_id' => $fournisseur->id]
            );

            throw ValidationException::withMessages([
                'gps' => ["Position GPS invalide. Distance {$gpsCheck['distance']} m (maximum {$gpsCheck['max']} m). Transaction bloquée."],
            ]);
        }

        // Enregistre la position du scan
        $jcode->setPositionScan($lat, $lng);

        // NOUVEAU : On ne paye pas immédiatement, on programme pour J+1
        $jcode->update([
            'fournisseur_id'  => $fournisseur->id,
            'statut'          => 'utilise',
            'scanned_at'      => now(),
            'paiement_status' => 'programme',
        ]);

        \App\Jobs\PaySupplierJob::dispatch($jcode->id)->delay(now()->addDay());

        $this->notificationService->send(
            $jcode->artisan,
            'validation',
            'Matériaux livrés',
            "Le fournisseur a validé votre J-Code {$jcode->code}. Paiement J+1 garanti.",
            ['jcode_id' => $jcode->id, 'mission_id' => $jcode->mission_id]
        );

        return [
            'valid'    => true,
            'distance' => $gpsCheck['distance'],
            'montant'  => $jcode->montant,
            'artisan'  => ['id' => $jcode->artisan_id, 'name' => $jcode->artisan->name],
        ];
    }

    /**
     * Génère un code PA-XXXX unique.
     */
    private function generateUniqueCode(): string
    {
        $prefix = config('prosartisan.jcode.prefix', 'PA-');
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I, O, 0, 1

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = $prefix . $suffix;
        } while (JCode::where('code', $code)->exists());

        return $code;
    }
}
