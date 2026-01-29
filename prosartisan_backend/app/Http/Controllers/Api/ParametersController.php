<?php

namespace App\Http\Controllers\Api;

use App\Domain\Shared\Services\SystemParameterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ParametersController extends Controller
{
 public function __construct(
  private SystemParameterService $parameterService
 ) {}

 /**
  * Get public parameters for mobile app
  */
 public function public(): JsonResponse
 {
  $parameters = $this->parameterService->getPublicParameters();

  $formattedParameters = $parameters->mapWithKeys(function ($parameter) {
   return [$parameter->key => $parameter->getTypedValue()];
  });

  return response()->json([
   'success' => true,
   'data' => $formattedParameters,
  ]);
 }

 /**
  * Get parameters by category
  */
 public function byCategory(string $category): JsonResponse
 {
  $parameters = $this->parameterService->getParametersByCategory($category)
   ->where('is_public', true);

  $formattedParameters = $parameters->mapWithKeys(function ($parameter) {
   return [$parameter->key => $parameter->getTypedValue()];
  });

  return response()->json([
   'success' => true,
   'data' => $formattedParameters,
  ]);
 }
}
