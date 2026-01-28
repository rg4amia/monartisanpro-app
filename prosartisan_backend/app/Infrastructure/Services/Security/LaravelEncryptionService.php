<?php

namespace App\Infrastructure\Services\Security;

use App\Domain\Shared\Services\EncryptionService;
use Exception;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Hash;

/**
 * Laravel implementation of EncryptionService
 *
 * Uses Laravel's built-in encryption (AES-256-CBC) and hashing
 *
 * Requirements: 13.1
 */
class LaravelEncryptionService implements EncryptionService
{
    public function __construct(
        private Encrypter $encrypter
    ) {}

    /**
     * {@inheritDoc}
     */
    public function encrypt(string $data): string
    {
        try {
            return $this->encrypter->encrypt($data);
        } catch (\Exception $e) {
            throw new Exception('Failed to encrypt data: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decrypt(string $encryptedData): string
    {
        try {
            return $this->encrypter->decrypt($encryptedData);
        } catch (\Exception $e) {
            throw new Exception('Failed to decrypt data: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hash(string $data, ?string $salt = null): string
    {
        if ($salt !== null) {
            $data = $salt.$data;
        }

        return Hash::make($data);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return Hash::check($data, $hash);
    }

    /**
     * {@inheritDoc}
     */
    public function generateKey(int $length = 32): string
    {
        return base64_encode(random_bytes($length));
    }

    /**
     * {@inheritDoc}
     */
    public function encryptFile(string $filePath, string $outputPath): bool
    {
        try {
            if (! file_exists($filePath)) {
                throw new Exception("Source file does not exist: {$filePath}");
            }

            $contents = file_get_contents($filePath);
            if ($contents === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            $encryptedContents = $this->encrypt($contents);

            $result = file_put_contents($outputPath, $encryptedContents);
            if ($result === false) {
                throw new Exception("Failed to write encrypted file: {$outputPath}");
            }

            return true;
        } catch (\Exception $e) {
            throw new Exception('Failed to encrypt file: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decryptFile(string $encryptedFilePath, string $outputPath): bool
    {
        try {
            if (! file_exists($encryptedFilePath)) {
                throw new Exception("Encrypted file does not exist: {$encryptedFilePath}");
            }

            $encryptedContents = file_get_contents($encryptedFilePath);
            if ($encryptedContents === false) {
                throw new Exception("Failed to read encrypted file: {$encryptedFilePath}");
            }

            $decryptedContents = $this->decrypt($encryptedContents);

            $result = file_put_contents($outputPath, $decryptedContents);
            if ($result === false) {
                throw new Exception("Failed to write decrypted file: {$outputPath}");
            }

            return true;
        } catch (\Exception $e) {
            throw new Exception('Failed to decrypt file: '.$e->getMessage(), 0, $e);
        }
    }
}
