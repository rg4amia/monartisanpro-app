<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for quote acceptance
 *
 * Requirements: 3.5, 3.6, 3.7
 */
class AcceptQuoteRequest extends FormRequest
{
 /**
  * Determine if the user is authorized to make this request.
  */
 public function authorize(): bool
 {
  return $this->user() && $this->user()->user_type === 'CLIENT';
 }

 /**
  * Get the validation rules that apply to the request.
  */
 public function rules(): array
 {
  // No additional validation needed for quote acceptance
  // Authorization and business logic are handled in the controller
  return [];
 }
}
