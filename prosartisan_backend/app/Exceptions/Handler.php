<?php

namespace App\Exceptions;

use App\Domain\Identity\Exceptions\AccountLockedException;
use App\Domain\Identity\Exceptions\AccountSuspendedException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\InvalidTokenException;
use App\Domain\Identity\Exceptions\OTPGenerationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // For API requests, always return JSON responses
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render API exceptions with consistent JSON format
     */
    protected function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        // Handle validation exceptions
        if ($e instanceof ValidationException) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'status_code' => 422,
                'validation_errors' => $e->errors(),
            ], 422);
        }

        // Handle authentication exceptions
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Authentication is required to access this resource.',
                'status_code' => 401,
            ], 401);
        }

        // Handle authorization exceptions
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
                'status_code' => 403,
            ], 403);
        }

        // Handle model not found exceptions
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'The requested resource was not found.',
                'status_code' => 404,
            ], 404);
        }

        // Handle not found HTTP exceptions
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'The requested endpoint was not found.',
                'status_code' => 404,
            ], 404);
        }

        // Handle method not allowed exceptions
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'error' => 'METHOD_NOT_ALLOWED',
                'message' => 'The HTTP method is not allowed for this endpoint.',
                'status_code' => 405,
            ], 405);
        }

        // Handle rate limiting exceptions
        if ($e instanceof TooManyRequestsHttpException) {
            return response()->json([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'Too many requests. Please try again later.',
                'status_code' => 429,
            ], 429);
        }

        // Handle domain-specific exceptions
        if ($e instanceof InvalidCredentialsException) {
            return response()->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => $e->getMessage(),
                'status_code' => 401,
            ], 401);
        }

        if ($e instanceof AccountLockedException) {
            return response()->json([
                'error' => 'ACCOUNT_LOCKED',
                'message' => $e->getMessage(),
                'status_code' => 423,
            ], 423);
        }

        if ($e instanceof AccountSuspendedException) {
            return response()->json([
                'error' => 'ACCOUNT_SUSPENDED',
                'message' => $e->getMessage(),
                'status_code' => 403,
            ], 403);
        }

        if ($e instanceof InvalidTokenException) {
            return response()->json([
                'error' => 'INVALID_TOKEN',
                'message' => $e->getMessage(),
                'status_code' => 401,
            ], 401);
        }

        if ($e instanceof OTPGenerationException) {
            return response()->json([
                'error' => 'OTP_GENERATION_FAILED',
                'message' => $e->getMessage(),
                'status_code' => 500,
            ], 500);
        }

        // Handle generic HTTP exceptions
        if (method_exists($e, 'getStatusCode')) {
            $statusCode = $e->getStatusCode();

            return response()->json([
                'error' => $this->getErrorTypeFromStatusCode($statusCode),
                'message' => $e->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode),
                'status_code' => $statusCode,
            ], $statusCode);
        }

        // Handle all other exceptions as internal server errors
        $message = config('app.debug') ? $e->getMessage() : 'An internal server error occurred.';

        return response()->json([
            'error' => 'INTERNAL_SERVER_ERROR',
            'message' => $message,
            'status_code' => 500,
        ], 500);
    }

    /**
     * Get error type from HTTP status code
     */
    protected function getErrorTypeFromStatusCode(int $statusCode): string
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
            default => 'HTTP_ERROR_'.$statusCode
        };
    }

    /**
     * Get default message for HTTP status code
     */
    protected function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'The request could not be understood by the server.',
            401 => 'Authentication is required to access this resource.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The requested resource was not found.',
            405 => 'The HTTP method is not allowed for this resource.',
            409 => 'The request conflicts with the current state of the resource.',
            422 => 'The request data failed validation.',
            423 => 'The resource is temporarily locked.',
            429 => 'Too many requests. Please try again later.',
            500 => 'An internal server error occurred.',
            502 => 'Bad gateway error.',
            503 => 'The service is temporarily unavailable.',
            504 => 'Gateway timeout error.',
            default => 'An HTTP error occurred.'
        };
    }
}
