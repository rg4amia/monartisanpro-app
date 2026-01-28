<?php

namespace App\Domain\Shared\Services;

/**
 * Encryption Service Interface
 *
 * Handles encryption and decryption of sensitive data using AES-256
 *
 * Requirements: 13.1
 */
interface EncryptionService
{
    /**
     * Encrypt sensitive data
     *
     * @param  string  $data  Plain text data to encrypt
     * @return string Encrypted data (base64 encoded)
     *
     * @throws \Exception If encryption fails
     */
    public function encrypt(string $data): string;

    /**
     * Decrypt sensitive data
     *
     * @param  string  $encryptedData  Encrypted data (base64 encoded)
     * @return string Decrypted plain text data
     *
     * @throws \Exception If decryption fails
     */
    public function decrypt(string $encryptedData): string;

    /**
     * Hash sensitive data (one-way)
     *
     * @param  string  $data  Data to hash
     * @param  string|null  $salt  Optional salt
     * @return string Hashed data
     */
    public function hash(string $data, ?string $salt = null): string;

    /**
     * Verify hashed data
     *
     * @param  string  $data  Plain text data
     * @param  string  $hash  Hashed data to verify against
     * @return bool True if data matches hash
     */
    public function verifyHash(string $data, string $hash): bool;

    /**
     * Generate a secure random key
     *
     * @param  int  $length  Key length in bytes
     * @return string Random key (base64 encoded)
     */
    public function generateKey(int $length = 32): string;

    /**
     * Encrypt file contents
     *
     * @param  string  $filePath  Path to file to encrypt
     * @param  string  $outputPath  Path to save encrypted file
     * @return bool True if successful
     */
    public function encryptFile(string $filePath, string $outputPath): bool;

    /**
     * Decrypt file contents
     *
     * @param  string  $encryptedFilePath  Path to encrypted file
     * @param  string  $outputPath  Path to save decrypted file
     * @return bool True if successful
     */
    public function decryptFile(string $encryptedFilePath, string $outputPath): bool;
}
