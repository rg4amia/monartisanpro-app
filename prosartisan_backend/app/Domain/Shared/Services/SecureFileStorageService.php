<?php

namespace App\Domain\Shared\Services;

use Illuminate\Http\UploadedFile;

/**
 * Secure File Storage Service Interface
 *
 * Handles secure storage of sensitive files (KYC documents, photos)
 * with encryption and access control
 *
 * Requirements: 13.1
 */
interface SecureFileStorageService
{
    /**
     * Store a file securely with encryption
     *
     * @param  UploadedFile  $file  File to store
     * @param  string  $directory  Directory to store in (e.g., 'kyc', 'proofs')
     * @param  string|null  $filename  Custom filename (optional)
     * @return string Secure file path/URL
     *
     * @throws \Exception If storage fails
     */
    public function storeSecurely(UploadedFile $file, string $directory, ?string $filename = null): string;

    /**
     * Retrieve and decrypt a secure file
     *
     * @param  string  $secureFilePath  Path returned by storeSecurely()
     * @return string Temporary file path to decrypted content
     *
     * @throws \Exception If retrieval fails
     */
    public function retrieveSecurely(string $secureFilePath): string;

    /**
     * Delete a secure file
     *
     * @param  string  $secureFilePath  Path to delete
     * @return bool True if successful
     */
    public function deleteSecurely(string $secureFilePath): bool;

    /**
     * Check if a secure file exists
     *
     * @param  string  $secureFilePath  Path to check
     * @return bool True if file exists
     */
    public function existsSecurely(string $secureFilePath): bool;

    /**
     * Get file metadata without decrypting content
     *
     * @param  string  $secureFilePath  Path to file
     * @return array Metadata (size, type, created_at, etc.)
     */
    public function getMetadata(string $secureFilePath): array;

    /**
     * Generate a secure temporary URL for file access
     *
     * @param  string  $secureFilePath  Path to file
     * @param  int  $expirationMinutes  URL expiration time
     * @return string Temporary URL
     */
    public function generateTemporaryUrl(string $secureFilePath, int $expirationMinutes = 60): string;

    /**
     * Validate file type and size
     *
     * @param  UploadedFile  $file  File to validate
     * @param  array  $allowedTypes  Allowed MIME types
     * @param  int  $maxSizeBytes  Maximum file size in bytes
     * @return bool True if valid
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateFile(UploadedFile $file, array $allowedTypes, int $maxSizeBytes): bool;
}
