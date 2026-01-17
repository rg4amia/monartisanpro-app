<?php

namespace App\Infrastructure\Services\Storage;

use App\Domain\Shared\Services\EncryptionService;
use App\Domain\Shared\Services\SecureFileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Exception;

/**
 * Local implementation of SecureFileStorageService
 *
 * Stores files encrypted on local filesystem with metadata
 *
 * Requirements: 13.1
 */
class LocalSecureFileStorageService implements SecureFileStorageService
{
 private const SECURE_DISK = 'secure';
 private const METADATA_SUFFIX = '.meta';
 private const TEMP_PREFIX = 'temp_decrypted_';

 public function __construct(
  private EncryptionService $encryptionService
 ) {}

 /**
  * {@inheritDoc}
  */
 public function storeSecurely(UploadedFile $file, string $directory, ?string $filename = null): string
 {
  try {
   // Generate secure filename if not provided
   if ($filename === null) {
    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
   }

   // Ensure filename is secure
   $filename = $this->sanitizeFilename($filename);
   $relativePath = $directory . '/' . $filename;

   // Read file contents
   $contents = file_get_contents($file->getRealPath());
   if ($contents === false) {
    throw new Exception('Failed to read uploaded file');
   }

   // Encrypt contents
   $encryptedContents = $this->encryptionService->encrypt($contents);

   // Store encrypted file
   $stored = Storage::disk(self::SECURE_DISK)->put($relativePath, $encryptedContents);
   if (!$stored) {
    throw new Exception('Failed to store encrypted file');
   }

   // Store metadata
   $metadata = [
    'original_name' => $file->getClientOriginalName(),
    'mime_type' => $file->getMimeType(),
    'size' => $file->getSize(),
    'created_at' => now()->toISOString(),
    'encrypted' => true,
   ];

   $metadataStored = Storage::disk(self::SECURE_DISK)->put(
    $relativePath . self::METADATA_SUFFIX,
    json_encode($metadata)
   );

   if (!$metadataStored) {
    // Clean up the file if metadata storage failed
    Storage::disk(self::SECURE_DISK)->delete($relativePath);
    throw new Exception('Failed to store file metadata');
   }

   return $relativePath;
  } catch (Exception $e) {
   throw new Exception('Failed to store file securely: ' . $e->getMessage(), 0, $e);
  }
 }

 /**
  * {@inheritDoc}
  */
 public function retrieveSecurely(string $secureFilePath): string
 {
  try {
   if (!$this->existsSecurely($secureFilePath)) {
    throw new Exception("Secure file not found: {$secureFilePath}");
   }

   // Read encrypted contents
   $encryptedContents = Storage::disk(self::SECURE_DISK)->get($secureFilePath);
   if ($encryptedContents === null) {
    throw new Exception('Failed to read encrypted file');
   }

   // Decrypt contents
   $decryptedContents = $this->encryptionService->decrypt($encryptedContents);

   // Create temporary file
   $tempPath = storage_path('app/temp/' . self::TEMP_PREFIX . Str::uuid());

   // Ensure temp directory exists
   $tempDir = dirname($tempPath);
   if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
   }

   // Write decrypted contents to temp file
   $written = file_put_contents($tempPath, $decryptedContents);
   if ($written === false) {
    throw new Exception('Failed to create temporary decrypted file');
   }

   return $tempPath;
  } catch (Exception $e) {
   throw new Exception('Failed to retrieve file securely: ' . $e->getMessage(), 0, $e);
  }
 }

 /**
  * {@inheritDoc}
  */
 public function deleteSecurely(string $secureFilePath): bool
 {
  try {
   $fileDeleted = Storage::disk(self::SECURE_DISK)->delete($secureFilePath);
   $metadataDeleted = Storage::disk(self::SECURE_DISK)->delete($secureFilePath . self::METADATA_SUFFIX);

   return $fileDeleted && $metadataDeleted;
  } catch (Exception $e) {
   return false;
  }
 }

 /**
  * {@inheritDoc}
  */
 public function existsSecurely(string $secureFilePath): bool
 {
  return Storage::disk(self::SECURE_DISK)->exists($secureFilePath) &&
   Storage::disk(self::SECURE_DISK)->exists($secureFilePath . self::METADATA_SUFFIX);
 }

 /**
  * {@inheritDoc}
  */
 public function getMetadata(string $secureFilePath): array
 {
  try {
   if (!$this->existsSecurely($secureFilePath)) {
    throw new Exception("Secure file not found: {$secureFilePath}");
   }

   $metadataJson = Storage::disk(self::SECURE_DISK)->get($secureFilePath . self::METADATA_SUFFIX);
   if ($metadataJson === null) {
    throw new Exception('Failed to read file metadata');
   }

   $metadata = json_decode($metadataJson, true);
   if ($metadata === null) {
    throw new Exception('Invalid metadata format');
   }

   return $metadata;
  } catch (Exception $e) {
   throw new Exception('Failed to get file metadata: ' . $e->getMessage(), 0, $e);
  }
 }

 /**
  * {@inheritDoc}
  */
 public function generateTemporaryUrl(string $secureFilePath, int $expirationMinutes = 60): string
 {
  // For local storage, we'll generate a signed URL that can be used
  // with a controller endpoint that handles secure file serving
  $token = Str::random(32);
  $expiresAt = now()->addMinutes($expirationMinutes)->timestamp;

  // Store temporary access token in cache
  cache()->put(
   "secure_file_access:{$token}",
   [
    'file_path' => $secureFilePath,
    'expires_at' => $expiresAt,
   ],
   now()->addMinutes($expirationMinutes)
  );

  return route('secure-file.serve', [
   'token' => $token,
   'expires' => $expiresAt,
   'signature' => hash_hmac('sha256', $token . $expiresAt, config('app.key')),
  ]);
 }

 /**
  * {@inheritDoc}
  */
 public function validateFile(UploadedFile $file, array $allowedTypes, int $maxSizeBytes): bool
 {
  // Check file size
  if ($file->getSize() > $maxSizeBytes) {
   throw new InvalidArgumentException(
    "File size ({$file->getSize()} bytes) exceeds maximum allowed size ({$maxSizeBytes} bytes)"
   );
  }

  // Check MIME type
  $mimeType = $file->getMimeType();
  if (!in_array($mimeType, $allowedTypes)) {
   throw new InvalidArgumentException(
    "File type ({$mimeType}) is not allowed. Allowed types: " . implode(', ', $allowedTypes)
   );
  }

  // Additional security checks
  $this->performSecurityChecks($file);

  return true;
 }

 /**
  * Sanitize filename to prevent directory traversal
  */
 private function sanitizeFilename(string $filename): string
 {
  // Remove directory separators and other dangerous characters
  $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

  // Ensure filename is not empty
  if (empty($filename)) {
   $filename = Str::uuid();
  }

  return $filename;
 }

 /**
  * Perform additional security checks on uploaded file
  */
 private function performSecurityChecks(UploadedFile $file): void
 {
  // Check for executable file extensions
  $dangerousExtensions = ['php', 'exe', 'bat', 'sh', 'cmd', 'scr', 'pif', 'jar'];
  $extension = strtolower($file->getClientOriginalExtension());

  if (in_array($extension, $dangerousExtensions)) {
   throw new InvalidArgumentException("Dangerous file extension not allowed: {$extension}");
  }

  // Check file signature matches extension (basic check)
  $this->validateFileSignature($file);
 }

 /**
  * Validate file signature matches claimed type
  */
 private function validateFileSignature(UploadedFile $file): void
 {
  $handle = fopen($file->getRealPath(), 'rb');
  if (!$handle) {
   throw new InvalidArgumentException('Cannot read file for signature validation');
  }

  $header = fread($handle, 8);
  fclose($handle);

  $mimeType = $file->getMimeType();

  // Basic signature validation for common file types
  $signatures = [
   'image/jpeg' => ["\xFF\xD8\xFF"],
   'image/png' => ["\x89\x50\x4E\x47"],
   'image/gif' => ["GIF87a", "GIF89a"],
   'application/pdf' => ["%PDF"],
  ];

  if (isset($signatures[$mimeType])) {
   $validSignature = false;
   foreach ($signatures[$mimeType] as $signature) {
    if (str_starts_with($header, $signature)) {
     $validSignature = true;
     break;
    }
   }

   if (!$validSignature) {
    throw new InvalidArgumentException('File signature does not match claimed type');
   }
  }
 }
}
