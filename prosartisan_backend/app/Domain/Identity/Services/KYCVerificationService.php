<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\KYCVerificationResult;

/**
 * Domain Service for KYC (Know Your Customer) verification
 *
 * Validates identity documents (CNI or PASSPORT) and selfie photos
 * to ensure user authenticity and compliance with regulations.
 *
 * **Validates: Requirements 1.2**
 */
interface KYCVerificationService
{
 /**
  * Verify KYC documents for authenticity and completeness
  *
  * Validates:
  * - Document type is CNI or PASSPORT
  * - ID number format is valid
  * - Document URL is accessible
  * - Selfie URL is accessible
  * - All required fields are present
  *
  * @param KYCDocuments $documents The KYC documents to verify
  * @return KYCVerificationResult The verification result with success/failure status
  */
 public function verifyDocuments(KYCDocuments $documents): KYCVerificationResult;
}
