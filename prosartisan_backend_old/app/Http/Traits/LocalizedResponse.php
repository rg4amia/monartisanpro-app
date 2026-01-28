<?php

namespace App\Http\Traits;

use App\Infrastructure\Services\Localization\LocalizationService;
use Illuminate\Http\JsonResponse;

trait LocalizedResponse
{
    /**
     * Return a localized success response
     */
    protected function successResponse(string $messageKey, array $data = [], array $messageParams = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __($messageKey, $messageParams),
            'data' => $this->localizeData($data),
        ]);
    }

    /**
     * Return a localized error response
     */
    protected function errorResponse(string $messageKey, array $messageParams = [], int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __($messageKey, $messageParams),
            'error' => [
                'code' => $statusCode,
                'message' => __($messageKey, $messageParams),
            ],
        ], $statusCode);
    }

    /**
     * Return a localized validation error response
     */
    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('validation.validation_failed'),
            'errors' => $errors,
        ], 422);
    }

    /**
     * Localize data arrays (format dates, currencies, etc.)
     */
    protected function localizeData(array $data): array
    {
        $localizationService = app(LocalizationService::class);

        return $this->recursivelyLocalizeData($data, $localizationService);
    }

    /**
     * Recursively localize data
     */
    private function recursivelyLocalizeData(array $data, LocalizationService $localizationService): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursivelyLocalizeData($value, $localizationService);
            } elseif ($this->shouldLocalizeField($key, $value)) {
                $data[$key] = $this->localizeFieldValue($key, $value, $localizationService);
            }
        }

        return $data;
    }

    /**
     * Check if field should be localized
     */
    private function shouldLocalizeField(string $key, $value): bool
    {
        // Currency fields (amounts in centimes)
        if (str_contains($key, 'amount') && is_numeric($value)) {
            return true;
        }

        // Date fields
        if (str_contains($key, '_at') || str_contains($key, 'date')) {
            return $value instanceof \DateTime || is_string($value);
        }

        return false;
    }

    /**
     * Localize field value
     */
    private function localizeFieldValue(string $key, $value, LocalizationService $localizationService)
    {
        // Format currency
        if (str_contains($key, 'amount') && is_numeric($value)) {
            return [
                'raw' => $value,
                'formatted' => $localizationService->formatCurrency((int) $value),
            ];
        }

        // Format dates
        if (str_contains($key, '_at') || str_contains($key, 'date')) {
            if ($value instanceof \DateTime) {
                return [
                    'raw' => $value->format('Y-m-d H:i:s'),
                    'formatted' => $localizationService->formatDateTime($value),
                    'relative' => $localizationService->formatRelativeTime($value),
                ];
            } elseif (is_string($value)) {
                try {
                    $date = new \DateTime($value);

                    return [
                        'raw' => $value,
                        'formatted' => $localizationService->formatDateTime($date),
                        'relative' => $localizationService->formatRelativeTime($date),
                    ];
                } catch (\Exception $e) {
                    return $value;
                }
            }
        }

        return $value;
    }

    /**
     * Get localized status text
     */
    protected function getLocalizedStatus(string $context, string $status): string
    {
        return __("{$context}.status.{$status}");
    }

    /**
     * Get localized category text
     */
    protected function getLocalizedCategory(string $context, string $category): string
    {
        return __("{$context}.categories.{$category}");
    }
}
