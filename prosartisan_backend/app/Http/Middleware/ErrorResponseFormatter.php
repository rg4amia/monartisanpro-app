<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Error Response Formatter Middleware
 *
 * Ensures all API error responses follow a consistent JSON format with:
 * - error: Error type identifier
 * - message: Human-readable error message
 * - status_code: HTTP status code
 */
class ErrorResponseFormatter
{
 /**
  * Handle an incoming request.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
  * @return \Symfony\Component\HttpFoundation\Response
  */
 public function handle(Request $request, Closure $next): SymfonyResponse
 {
  $response = $next($request);

  // Only format JSON responses for API routes
  if (!$request->is('api/*') || !$this->shouldFormatResponse($response)) {
   return $response;
  }

  // If response is already properly formatted, return as is
  if ($this->isAlreadyFormatted($response)) {
   return $response;
  }

  // Format error responses
  if ($this->isErrorResponse($response)) {
   return $this->formatErrorResponse($response);
  }

  return $response;
 }

 /**
  * Check if the response should be formatted
  */
 private function shouldFormatResponse(SymfonyResponse $response): bool
 {
  return $response instanceof JsonResponse ||
   $response->headers->get('Content-Type') === 'application/json';
 }

 /**
  * Check if the response is an error response
  */
 private function isErrorResponse(SymfonyResponse $response): bool
 {
  return $response->getStatusCode() >= 400;
 }

 /**
  * Check if the response is already properly formatted
  */
 private function isAlreadyFormatted(SymfonyResponse $response): bool
 {
  if (!$response instanceof JsonResponse) {
   return false;
  }

  $data = json_decode($response->getContent(), true);

  // Check if it already has our standard error format
  return is_array($data) &&
   isset($data['error']) &&
   isset($data['message']) &&
   isset($data['status_code']);
 }

 /**
  * Format the error response to follow consistent structure
  */
 private function formatErrorResponse(SymfonyResponse $response): JsonResponse
 {
  $statusCode = $response->getStatusCode();
  $content = $response->getContent();

  // Try to decode existing JSON content
  $existingData = json_decode($content, true);

  // Determine error type and message
  $errorData = $this->extractErrorData($existingData, $statusCode);

  $formattedResponse = [
   'error' => $errorData['error'],
   'message' => $errorData['message'],
   'status_code' => $statusCode
  ];

  // Include validation errors if present
  if (isset($existingData['errors'])) {
   $formattedResponse['validation_errors'] = $existingData['errors'];
  }

  // Include additional data if present (but not overriding our standard fields)
  if (is_array($existingData)) {
   foreach ($existingData as $key => $value) {
    if (!in_array($key, ['error', 'message', 'status_code', 'errors'])) {
     $formattedResponse[$key] = $value;
    }
   }
  }

  return response()->json($formattedResponse, $statusCode);
 }

 /**
  * Extract error type and message from existing data or generate defaults
  */
 private function extractErrorData(?array $existingData, int $statusCode): array
 {
  // If existing data already has error and message, use them
  if (is_array($existingData) && isset($existingData['error']) && isset($existingData['message'])) {
   return [
    'error' => $existingData['error'],
    'message' => $existingData['message']
   ];
  }

  // If existing data has message but no error type, generate error type
  if (is_array($existingData) && isset($existingData['message'])) {
   return [
    'error' => $this->generateErrorType($statusCode),
    'message' => $existingData['message']
   ];
  }

  // Generate default error type and message based on status code
  return $this->getDefaultErrorData($statusCode);
 }

 /**
  * Generate error type based on status code
  */
 private function generateErrorType(int $statusCode): string
 {
  return match ($statusCode) {
   400 => 'BAD_REQUEST',
   401 => 'UNAUTHORIZED',
   403 => 'FORBIDDEN',
   404 => 'NOT_FOUND',
   405 => 'METHOD_NOT_ALLOWED',
   409 => 'CONFLICT',
   422 => 'VALIDATION_ERROR',
   423 => 'LOCKED',
   429 => 'TOO_MANY_REQUESTS',
   500 => 'INTERNAL_SERVER_ERROR',
   502 => 'BAD_GATEWAY',
   503 => 'SERVICE_UNAVAILABLE',
   504 => 'GATEWAY_TIMEOUT',
   default => 'HTTP_ERROR_' . $statusCode
  };
 }

 /**
  * Get default error data for common HTTP status codes
  */
 private function getDefaultErrorData(int $statusCode): array
 {
  return match ($statusCode) {
   400 => [
    'error' => 'BAD_REQUEST',
    'message' => 'The request could not be understood by the server.'
   ],
   401 => [
    'error' => 'UNAUTHORIZED',
    'message' => 'Authentication is required to access this resource.'
   ],
   403 => [
    'error' => 'FORBIDDEN',
    'message' => 'You do not have permission to access this resource.'
   ],
   404 => [
    'error' => 'NOT_FOUND',
    'message' => 'The requested resource was not found.'
   ],
   405 => [
    'error' => 'METHOD_NOT_ALLOWED',
    'message' => 'The HTTP method is not allowed for this resource.'
   ],
   409 => [
    'error' => 'CONFLICT',
    'message' => 'The request conflicts with the current state of the resource.'
   ],
   422 => [
    'error' => 'VALIDATION_ERROR',
    'message' => 'The request data failed validation.'
   ],
   423 => [
    'error' => 'LOCKED',
    'message' => 'The resource is temporarily locked.'
   ],
   429 => [
    'error' => 'TOO_MANY_REQUESTS',
    'message' => 'Too many requests. Please try again later.'
   ],
   500 => [
    'error' => 'INTERNAL_SERVER_ERROR',
    'message' => 'An internal server error occurred.'
   ],
   502 => [
    'error' => 'BAD_GATEWAY',
    'message' => 'Bad gateway error.'
   ],
   503 => [
    'error' => 'SERVICE_UNAVAILABLE',
    'message' => 'The service is temporarily unavailable.'
   ],
   504 => [
    'error' => 'GATEWAY_TIMEOUT',
    'message' => 'Gateway timeout error.'
   ],
   default => [
    'error' => 'HTTP_ERROR_' . $statusCode,
    'message' => 'An HTTP error occurred.'
   ]
  };
 }
}
