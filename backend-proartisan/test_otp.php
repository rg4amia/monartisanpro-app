<?php

/**
 * Script de test OTP — à exécuter via: php artisan tinker < test_otp.php
 */

use App\Services\OtpService;

$phone = '0141498409';
$action = 'test_verification';

echo "📱 Test envoi OTP à {$phone}...\n";
echo "   Canal: SMS (via SmsPro Africa)\n";
echo "   Action: {$action}\n\n";

$otpService = app(OtpService::class);
$code = $otpService->sendOtp($phone, $action);

echo "✅ OTP généré et envoyé: {$code}\n\n";

// Vérifier que l'OTP est bien stocké en base
$otpRecord = \App\Models\Otp::where('phone', $phone)
    ->where('code', $code)
    ->whereNull('used_at')
    ->latest()
    ->first();

if ($otpRecord) {
    echo "📋 Enregistrement OTP en base:\n";
    echo "   ID:         {$otpRecord->id}\n";
    echo "   Phone:      {$otpRecord->phone}\n";
    echo "   Code:       {$otpRecord->code}\n";
    echo "   Action:     {$otpRecord->action}\n";
    echo "   Expire à:   {$otpRecord->expires_at}\n";
    echo "   Utilisé:    " . ($otpRecord->used_at ?? 'Non') . "\n\n";
} else {
    echo "❌ OTP non trouvé en base!\n\n";
}

// Tester la vérification
echo "🔐 Test vérification OTP ({$code})... ";
$valid = $otpService->verifyOtp($phone, $code, $action);
echo $valid ? "✅ Valide!\n" : "❌ Invalide!\n";

// Vérifier qu'il est bien marqué comme utilisé
echo "🔐 Test réutilisation du même OTP... ";
$reuse = $otpService->verifyOtp($phone, $code, $action);
echo $reuse ? "⚠️ Réutilisable (problème!)\n" : "✅ Bien invalidé (non réutilisable)\n";
