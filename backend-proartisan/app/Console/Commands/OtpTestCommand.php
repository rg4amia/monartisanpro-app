<?php

namespace App\Console\Commands;

use App\Services\OtpService;
use App\Models\Otp;
use Illuminate\Console\Command;

class OtpTestCommand extends Command
{
    protected $signature = 'otp:test {phone : Numéro de téléphone destinataire}';
    protected $description = 'Tester le flux complet OTP : génération, envoi SMS, vérification, anti-réutilisation';

    public function handle(OtpService $otpService): int
    {
        $phone = $this->argument('phone');
        $action = 'test_verification';

        $this->info("📱 Test envoi OTP à {$phone}...");
        $this->line("   Canal: SMS (via SmsPro Africa)");
        $this->line("   Action: {$action}");
        $this->newLine();

        // 1. Générer et envoyer l'OTP
        $code = $otpService->sendOtp($phone, $action);
        $this->info("✅ OTP généré et envoyé: {$code}");
        $this->newLine();

        // 2. Vérifier le stockage en base
        $otpRecord = Otp::where('phone', $phone)
            ->where('code', $code)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($otpRecord) {
            $this->info('📋 Enregistrement OTP en base:');
            $this->table(
                ['Champ', 'Valeur'],
                [
                    ['ID', $otpRecord->id],
                    ['Phone', $otpRecord->phone],
                    ['Code', $otpRecord->code],
                    ['Action', $otpRecord->action],
                    ['Expire à', $otpRecord->expires_at],
                    ['Utilisé', $otpRecord->used_at ?? 'Non'],
                ]
            );
        } else {
            $this->error('❌ OTP non trouvé en base!');
            return self::FAILURE;
        }

        // 3. Tester la vérification
        $this->info("🔐 Test vérification OTP ({$code})...");
        $valid = $otpService->verifyOtp($phone, $code, $action);
        if ($valid) {
            $this->info('   ✅ Code valide — vérification réussie!');
        } else {
            $this->error('   ❌ Code invalide!');
            return self::FAILURE;
        }

        // 4. Tester l'anti-réutilisation
        $this->info("🔐 Test réutilisation du même OTP ({$code})...");
        $reuse = $otpService->verifyOtp($phone, $code, $action);
        if (!$reuse) {
            $this->info('   ✅ Bien invalidé — OTP non réutilisable');
        } else {
            $this->error('   ⚠️ OTP réutilisable — problème de sécurité!');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('🎉 Tous les tests OTP sont passés avec succès!');

        return self::SUCCESS;
    }
}
