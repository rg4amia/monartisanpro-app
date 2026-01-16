<?php

namespace Tests\Unit\Domain\Identity\Services;

use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Services\DefaultKYCVerificationService;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KYC Verification Service
 *
 * **Validates: Requirements 1.2**
 */
class KYCVerificationServiceTest extends TestCase
{
 private DefaultKYCVerificationService $service;

 protected function setUp(): void
 {
  parent::setUp();
  $this->service = new DefaultKYCVerificationService();
 }

 public function test_verifies_valid_cni_documents(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
  $this->assertFalse($result->hasErrors());
  $this->assertEmpty($result->getValidationErrors());
  $this->assertNotNull($result->getVerifiedAt());
 }

 public function test_verifies_valid_passport_documents(): void
 {
  $documents = new KYCDocuments(
   idType: 'PASSPORT',
   idNumber: 'AB123456',
   idDocumentUrl: 'https://example.com/documents/passport.pdf',
   selfieUrl: 'https://example.com/documents/selfie.png'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
  $this->assertFalse($result->hasErrors());
  $this->assertEmpty($result->getValidationErrors());
 }

 public function test_rejects_cni_number_too_short(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI123', // Only 5 characters, minimum is 8
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertCount(1, $result->getValidationErrors());
  $this->assertStringContainsString('at least 8 characters', $result->getValidationErrors()[0]);
 }

 public function test_rejects_cni_number_too_long(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI1234567890123', // 15 characters, maximum is 12
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('not exceed 12 characters', $result->getValidationErrors()[0]);
 }

 public function test_rejects_cni_number_with_invalid_characters(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI-123456', // Contains hyphen
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('uppercase letters and numbers', $result->getValidationErrors()[0]);
 }

 public function test_rejects_passport_number_too_short(): void
 {
  $documents = new KYCDocuments(
   idType: 'PASSPORT',
   idNumber: 'AB12', // Only 4 characters, minimum is 6
   idDocumentUrl: 'https://example.com/documents/passport.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('at least 6 characters', $result->getValidationErrors()[0]);
 }

 public function test_rejects_passport_number_too_long(): void
 {
  $documents = new KYCDocuments(
   idType: 'PASSPORT',
   idNumber: 'AB1234567890', // 12 characters, maximum is 9
   idDocumentUrl: 'https://example.com/documents/passport.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('not exceed 9 characters', $result->getValidationErrors()[0]);
 }

 public function test_rejects_passport_number_with_invalid_characters(): void
 {
  $documents = new KYCDocuments(
   idType: 'PASSPORT',
   idNumber: 'AB@12345', // Contains special character
   idDocumentUrl: 'https://example.com/documents/passport.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('uppercase letters and numbers', $result->getValidationErrors()[0]);
 }

 public function test_rejects_invalid_document_url(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'not-a-valid-url', // Invalid URL
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('ID document URL must be a valid URL', $result->getValidationErrors()[0]);
 }

 public function test_rejects_invalid_selfie_url(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'invalid-selfie-url' // Invalid URL
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('Selfie URL must be a valid URL', $result->getValidationErrors()[0]);
 }

 public function test_accepts_local_file_paths(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: '/storage/documents/cni.jpg',
   selfieUrl: '/storage/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
  $this->assertFalse($result->hasErrors());
 }

 public function test_rejects_invalid_file_extensions(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.txt', // Invalid extension
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('valid image or PDF file', $result->getValidationErrors()[0]);
 }

 public function test_accepts_valid_image_extensions(): void
 {
  $extensions = ['jpg', 'jpeg', 'png', 'pdf'];

  foreach ($extensions as $ext) {
   $documents = new KYCDocuments(
    idType: 'CNI',
    idNumber: 'CI12345678',
    idDocumentUrl: "https://example.com/documents/cni.{$ext}",
    selfieUrl: "https://example.com/documents/selfie.{$ext}"
   );

   $result = $this->service->verifyDocuments($documents);

   $this->assertTrue(
    $result->isVerified(),
    "Extension .{$ext} should be accepted"
   );
  }
 }

 public function test_rejects_future_submission_timestamp(): void
 {
  $futureDate = new DateTime('+1 day');

  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg',
   submittedAt: $futureDate
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertStringContainsString('cannot be in the future', $result->getValidationErrors()[0]);
 }

 public function test_accepts_past_submission_timestamp(): void
 {
  $pastDate = new DateTime('-1 day');

  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg',
   submittedAt: $pastDate
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
  $this->assertFalse($result->hasErrors());
 }

 public function test_collects_multiple_validation_errors(): void
 {
  $futureDate = new DateTime('+1 day');

  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI123', // Too short
   idDocumentUrl: 'invalid-url', // Invalid URL
   selfieUrl: 'also-invalid', // Invalid URL
   submittedAt: $futureDate // Future date
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertFalse($result->isVerified());
  $this->assertTrue($result->hasErrors());
  $this->assertGreaterThanOrEqual(4, count($result->getValidationErrors()));
 }

 public function test_verification_result_to_array(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);
  $array = $result->toArray();

  $this->assertIsArray($array);
  $this->assertArrayHasKey('is_verified', $array);
  $this->assertArrayHasKey('validation_errors', $array);
  $this->assertArrayHasKey('verified_at', $array);
  $this->assertTrue($array['is_verified']);
  $this->assertEmpty($array['validation_errors']);
  $this->assertNotNull($array['verified_at']);
 }

 public function test_handles_lowercase_id_type(): void
 {
  // KYCDocuments should normalize to uppercase, but test the service handles it
  $documents = new KYCDocuments(
   idType: 'cni', // lowercase
   idNumber: 'CI12345678',
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
 }

 public function test_handles_mixed_case_id_number(): void
 {
  $documents = new KYCDocuments(
   idType: 'CNI',
   idNumber: 'ci12345678', // lowercase
   idDocumentUrl: 'https://example.com/documents/cni.jpg',
   selfieUrl: 'https://example.com/documents/selfie.jpg'
  );

  $result = $this->service->verifyDocuments($documents);

  $this->assertTrue($result->isVerified());
 }
}
