<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\KYCVerificationResult;

/**
 * Default implementation of KYC verification service
 *
 * Performs document validation for CNI and PASSPORT identity documents.
 * This implementation validates document structure and format.
 *
 * **Validates: Requirements 1.2**
 */
class DefaultKYCVerificationService implements KYCVerificationService
{
    // CNI format: Alphanumeric, typically 8-12 characters for Côte d'Ivoire
    private const CNI_MIN_LENGTH = 8;
    private const CNI_MAX_LENGTH = 12;
    private const CNI_PATTERN = '/^[A-Z0-9]+$/';

    // Passport format: Alphanumeric, typically 6-9 characters
    private const PASSPORT_MIN_LENGTH = 6;
    private const PASSPORT_MAX_LENGTH = 9;
    private const PASSPORT_PATTERN = '/^[A-Z0-9]+$/';

    /**
     * Verify KYC documents for authenticity and completeness
     *
     * @param KYCDocuments $documents The KYC documents to verify
     * @return KYCVerificationResult The verification result
     */
    public function verifyDocuments(KYCDocuments $documents): KYCVerificationResult
    {
        $errors = [];

        // Validate ID number format based on document type
        if ($documents->isCNI()) {
            $idNumberErrors = $this->validateCNINumber($documents->getIdNumber());
            $errors = array_merge($errors, $idNumberErrors);
        } elseif ($documents->isPassport()) {
            $idNumberErrors = $this->validatePassportNumber($documents->getIdNumber());
            $errors = array_merge($errors, $idNumberErrors);
        }

        // Validate document URL accessibility
        $documentUrlErrors = $this->validateDocumentUrl($documents->getIdDocumentUrl(), 'ID document');
        $errors = array_merge($errors, $documentUrlErrors);

        // Validate selfie URL accessibility
        $selfieUrlErrors = $this->validateDocumentUrl($documents->getSelfieUrl(), 'Selfie');
        $errors = array_merge($errors, $selfieUrlErrors);

        // Validate submission timestamp is not in the future
        $timestampErrors = $this->validateSubmissionTimestamp($documents->getSubmittedAt());
        $errors = array_merge($errors, $timestampErrors);

        // Return result based on validation
        if (empty($errors)) {
            return KYCVerificationResult::success();
        }

        return KYCVerificationResult::failure($errors);
    }

    /**
     * Validate CNI (Carte Nationale d'Identité) number format
     *
     * @param string $idNumber The CNI number to validate
     * @return array Array of validation errors (empty if valid)
     */
    private function validateCNINumber(string $idNumber): array
    {
        $errors = [];
        $idNumber = strtoupper(trim($idNumber));

        // Check length
        $length = strlen($idNumber);
        if ($length < self::CNI_MIN_LENGTH) {
            $errors[] = sprintf(
                'CNI number must be at least %d characters long (got %d)',
                self::CNI_MIN_LENGTH,
                $length
            );
        }

        if ($length > self::CNI_MAX_LENGTH) {
            $errors[] = sprintf(
                'CNI number must not exceed %d characters (got %d)',
                self::CNI_MAX_LENGTH,
                $length
            );
        }

        // Check format (alphanumeric only)
        if (!preg_match(self::CNI_PATTERN, $idNumber)) {
            $errors[] = 'CNI number must contain only uppercase letters and numbers';
        }

        return $errors;
    }

    /**
     * Validate PASSPORT number format
     *
     * @param string $idNumber The passport number to validate
     * @return array Array of validation errors (empty if valid)
     */
    private function validatePassportNumber(string $idNumber): array
    {
        $errors = [];
        $idNumber = strtoupper(trim($idNumber));

        // Check length
        $length = strlen($idNumber);
        if ($length < self::PASSPORT_MIN_LENGTH) {
            $errors[] = sprintf(
                'Passport number must be at least %d characters long (got %d)',
                self::PASSPORT_MIN_LENGTH,
                $length
            );
        }

        if ($length > self::PASSPORT_MAX_LENGTH) {
            $errors[] = sprintf(
                'Passport number must not exceed %d characters (got %d)',
                self::PASSPORT_MAX_LENGTH,
                $length
            );
        }

        // Check format (alphanumeric only)
        if (!preg_match(self::PASSPORT_PATTERN, $idNumber)) {
            $errors[] = 'Passport number must contain only uppercase letters and numbers';
        }

        return $errors;
    }

    /**
     * Validate document URL is accessible
     *
     * This performs basic URL validation. In production, this could be enhanced
     * to check if the file exists in storage, validate file type, etc.
     *
     * @param string $url The document URL to validate
     * @param string $documentType The type of document (for error messages)
     * @return array Array of validation errors (empty if valid)
     */
    private function validateDocumentUrl(string $url, string $documentType): array
    {
        $errors = [];

        // Check URL is not empty
        if (empty(trim($url))) {
            $errors[] = "{$documentType} URL cannot be empty";
            return $errors;
        }

        // Validate URL format or path
        $isValidUrl = filter_var($url, FILTER_VALIDATE_URL) !== false;
        $isValidPath = str_starts_with($url, '/');

        if (!$isValidUrl && !$isValidPath) {
            $errors[] = "{$documentType} URL must be a valid URL or file path";
        }

        // Check for common image extensions (basic validation)
        $validExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        if (!empty($extension) && !in_array($extension, $validExtensions, true)) {
            $errors[] = sprintf(
                '%s must be a valid image or PDF file (got .%s)',
                $documentType,
                $extension
            );
        }

        return $errors;
    }

    /**
     * Validate submission timestamp is not in the future
     *
     * @param \DateTime $submittedAt The submission timestamp
     * @return array Array of validation errors (empty if valid)
     */
    private function validateSubmissionTimestamp(\DateTime $submittedAt): array
    {
        $errors = [];
        $now = new \DateTime();

        if ($submittedAt > $now) {
            $errors[] = sprintf(
                'Submission timestamp cannot be in the future (submitted: %s, current: %s)',
                $submittedAt->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s')
            );
        }

        return $errors;
    }
}
