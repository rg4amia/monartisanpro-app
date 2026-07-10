<?php

namespace App\Services;

class Google2faService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Génère la clé secrète aléatoire.
     */
    public function generateSecretKey(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Génère le code TOTP actuel pour le secret donné.
     */
    public function getCurrentCode(string $secret): string
    {
        $secretBinary = $this->base32Decode($secret);
        $timeWindow = (int) floor(time() / 30);
        return $this->calculateOtp($secretBinary, $timeWindow);
    }

    /**
     * Génère l'URL otpauth:// pour le scan de l'application Google Authenticator.
     */
    public function getQrCodeUrl(string $userEmailOrPhone, string $secret): string
    {
        $issuer = 'ProsArtisan';
        $label = rawurlencode($issuer . ':' . $userEmailOrPhone);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer);
    }

    /**
     * Vérifie si le code TOTP fourni est valide pour le secret donné.
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = str_replace(' ', '', $code);
        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $secretBinary = $this->base32Decode($secret);
        if ($secretBinary === false) {
            return false;
        }

        $currentTimeWindow = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timeWindow = $currentTimeWindow + $i;
            if ($this->calculateOtp($secretBinary, $timeWindow) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcule le code OTP pour une fenêtre de temps donnée.
     */
    private function calculateOtp(string $secretBinary, int $timeWindow): string
    {
        // Pack time window into a 64-bit binary string (big-endian)
        $timeBinary = pack('N*', 0) . pack('N*', $timeWindow);

        // Hash using HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Décode une chaîne Base32 en binaire.
     */
    private function base32Decode(string $base32): string|bool
    {
        $base32 = strtoupper(trim($base32));
        $base32 = str_replace('=', '', $base32);
        
        $allowedCharacters = self::BASE32_ALPHABET;
        for ($i = 0; $i < strlen($base32); $i++) {
            if (strpos($allowedCharacters, $base32[$i]) === false) {
                return false;
            }
        }

        $binaryString = '';
        foreach (str_split($base32) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);
            $binaryString .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $octets = str_split($binaryString, 8);
        $result = '';
        foreach ($octets as $octet) {
            if (strlen($octet) === 8) {
                $result .= chr(bindec($octet));
            }
        }

        return $result;
    }
}
