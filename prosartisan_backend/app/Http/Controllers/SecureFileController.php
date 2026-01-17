<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Services\SecureFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for serving secure files with temporary access tokens
 *
 * Requirements: 13.1
 */
class SecureFileController extends Controller
{
 public function __construct(
  private SecureFileStorageService $secureFileStorage
 ) {}

 /**
  * Serve a secure file using temporary access token
  */
 public function serve(Request $request): Response|BinaryFileResponse
 {
  $token = $request->get('token');
  $expires = $request->get('expires');
  $signature = $request->get('signature');

  // Validate signature
  $expectedSignature = hash_hmac('sha256', $token . $expires, config('app.key'));
  if (!hash_equals($expectedSignature, $signature)) {
   return response('Invalid signature', 403);
  }

  // Check expiration
  if (time() > $expires) {
   return response('Link expired', 410);
  }

  // Get file info from cache
  $cacheKey = "secure_file_access:{$token}";
  $fileInfo = Cache::get($cacheKey);

  if (!$fileInfo) {
   return response('Invalid or expired token', 404);
  }

  try {
   // Get file metadata
   $metadata = $this->secureFileStorage->getMetadata($fileInfo['file_path']);

   // Retrieve and decrypt file
   $tempFilePath = $this->secureFileStorage->retrieveSecurely($fileInfo['file_path']);

   // Create response with proper headers
   $response = new BinaryFileResponse($tempFilePath);
   $response->headers->set('Content-Type', $metadata['mime_type']);
   $response->headers->set('Content-Disposition', 'inline; filename="' . $metadata['original_name'] . '"');
   $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
   $response->headers->set('Pragma', 'no-cache');
   $response->headers->set('Expires', '0');

   // Clean up temp file after response is sent
   $response->deleteFileAfterSend(true);

   // Remove token from cache after use
   Cache::forget($cacheKey);

   return $response;
  } catch (\Exception $e) {
   return response('File not found or access denied', 404);
  }
 }
}
