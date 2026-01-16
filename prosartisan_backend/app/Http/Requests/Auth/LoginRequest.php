<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for user login
 *
 * Requirements: 1.3
 */
class LoginRequest extends FormRequest
{
 /**
  * Determine if the user is authorized to make this request.
  */
 public function authorize(): bool
 {
  return true;
 }

 /**
  * Get the validation rules that apply to the request.
  *
  * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
  */
 public function rules(): array
 {
  return [
   'email' => ['required', 'email', 'max:255'],
   'password' => ['required', 'string'],
  ];
 }

 /**
  * Get custom messages for validator errors.
  *
  * @return array<string, string>
  */
 public function messages(): array
 {
  return [
   'email.required' => 'L\'adresse email est requise.',
   'email.email' => 'L\'adresse email doit être valide.',
   'password.required' => 'Le mot de passe est requis.',
  ];
 }
}
