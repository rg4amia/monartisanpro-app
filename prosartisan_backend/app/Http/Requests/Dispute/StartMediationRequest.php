<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for starting mediation
 *
 * Requirement 9.3: Validate mediation start data
 */
class StartMediationRequest extends FormRequest
{
 public function authorize(): bool
 {
  return auth()->check();
 }

 public function rules(): array
 {
  return [
   'mediator_id' => ['required', 'uuid', 'exists:users,id'],
  ];
 }

 public function messages(): array
 {
  return [
   'mediator_id.required' => 'L\'ID du médiateur est requis',
   'mediator_id.uuid' => 'L\'ID du médiateur doit être un UUID valide',
   'mediator_id.exists' => 'Le médiateur spécifié n\'existe pas',
  ];
 }
}
