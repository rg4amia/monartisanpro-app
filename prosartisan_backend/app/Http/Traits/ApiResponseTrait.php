<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * API Response Trait
 *
 * Provides consistent methods for returning API responses
 */
trait ApiResponseTrait
{
 /**
  * Return a success response
  */
 protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
 {
  $response = [
   'success' => true,
   'message' => $message,
   'status_code' => $statusCode
  ];

  if ($data !== null) {
   $response['data'] = $data;
  }

  return response()->json($response, $statusCode);
 }

 /**
  * Return an error response with consistent format
  */
 protected function errorResponse(string $error, string $message, int $statusCode = 400, array $additionalData = []): JsonResponse
 {
  $response = [
   'error' => $error,
   'message' => $message,
   'status_code' => $statusCode
  ];

  // Add any additional data
  foreach ($additionalData as $key => $value) {
   $response[$key] = $value;
  }

  return response()->json($response, $statusCode);
 }

 /**
  * Return a validation error response
  */
 protected function validationErrorResponse(array $errors, string $message = 'The given data was invalid.'): JsonResponse
 {
  return $this->errorResponse(
   'VALIDATION_ERROR',
   $message,
   422,
   ['validation_errors' => $errors]
  );
 }

 /**
  * Return an unauthorized error response
  */
 protected function unauthorizedResponse(string $message = 'Authentication is required to access this resource.'): JsonResponse
 {
  return $this->errorResponse('UNAUTHORIZED', $message, 401);
 }

 /**
  * Return a forbidden error response
  */
 protected function forbiddenResponse(string $message = 'You do not have permission to access this resource.'): JsonResponse
 {
  return $this->errorResponse('FORBIDDEN', $message, 403);
 }

 /**
  * Return a not found error response
  */
 protected function notFoundResponse(string $message = 'The requested resource was not found.'): JsonResponse
 {
  return $this->errorResponse('NOT_FOUND', $message, 404);
 }

 /**
  * Return a conflict error response
  */
 protected function conflictResponse(string $message = 'The request conflicts with the current state of the resource.'): JsonResponse
 {
  return $this->errorResponse('CONFLICT', $message, 409);
 }

 /**
  * Return an internal server error response
  */
 protected function internalServerErrorResponse(string $message = 'An internal server error occurred.'): JsonResponse
 {
  return $this->errorResponse('INTERNAL_SERVER_ERROR', $message, 500);
 }

 /**
  * Return a paginated response
  */
 protected function paginatedResponse($paginatedData, string $message = 'Data retrieved successfully'): JsonResponse
 {
  return response()->json([
   'success' => true,
   'message' => $message,
   'status_code' => 200,
   'data' => $paginatedData->items(),
   'meta' => [
    'current_page' => $paginatedData->currentPage(),
    'per_page' => $paginatedData->perPage(),
    'total' => $paginatedData->total(),
    'last_page' => $paginatedData->lastPage(),
    'from' => $paginatedData->firstItem(),
    'to' => $paginatedData->lastItem()
   ]
  ]);
 }
}
