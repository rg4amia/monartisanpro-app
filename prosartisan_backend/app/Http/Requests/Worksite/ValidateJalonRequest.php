<?php

namespace App\Http\Requests\Worksite;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for validating a jalon
 *
 * Requirement 6.3: Validate jalon validation request
 */
class ValidateJalonRequest extends FormRequest
{
 public function authorize(): bool
 {
  return true; // Authorization handled by middleware
 }

 public function rules(): array
 {
  return [
   'comment' => ['sometimes', 'string', 'max:500'],
  ];
 }

 public function messages(): array
 {
  return [
   'comment.string' => 'Le commentaire doit être une chaîne de caractères',
   'comment.max' => 'Le commentaire ne peut pas dépasser 500 caractères',
  ];
 }
}
