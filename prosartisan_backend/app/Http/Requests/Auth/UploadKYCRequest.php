<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for KYC document upload
 *
 * Requirements: 1.2
 */
class UploadKYCRequest extends FormRequest
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
   'id_type' => ['required', 'string', Rule::in(['CNI', 'PASSPORT'])],
   'id_number' => ['required', 'string', 'max:100'],
   'id_document' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'], // 5MB max
   'selfie' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'], // 5MB max
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
   'id_type.required' => 'Le type de document d\'identité est requis.',
   'id_type.in' => 'Le type de document doit être CNI ou PASSPORT.',
   'id_number.required' => 'Le numéro du document d\'identité est requis.',
   'id_document.required' => 'Le document d\'identité est requis.',
   'id_document.file' => 'Le document d\'identité doit être un fichier.',
   'id_document.mimes' => 'Le document d\'identité doit être au format JPEG, JPG, PNG ou PDF.',
   'id_document.max' => 'Le document d\'identité ne doit pas dépasser 5 Mo.',
   'selfie.required' => 'Le selfie est requis.',
   'selfie.file' => 'Le selfie doit être un fichier.',
   'selfie.mimes' => 'Le selfie doit être au format JPEG, JPG ou PNG.',
   'selfie.max' => 'Le selfie ne doit pas dépasser 5 Mo.',
  ];
 }
}
