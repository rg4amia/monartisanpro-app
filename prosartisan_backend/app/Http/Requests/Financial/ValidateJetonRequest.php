<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for validating jeton
 *
 * Requirements: 5.3
 */
class ValidateJetonRequest extends FormRequest
{
 /**
  * Determine if the user is authorized to make this request.
  */
 public function authorize(): bool
 {
  return true; // Authorization handled by middleware
 }

 /**
  * Get the validation rules that apply to the request.
  *
  * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
  */
 public function rules(): array
 {
  return [
   'jeton_code' => 'required|string|regex:/^PA-[A-Z0-9]{4}$/',
   'fournisseur_id' => 'required|uuid|exists:users,id',
   'amount_centimes' => 'required|integer|min:100', // Minimum 1 XOF
   'artisan_latitude' => 'required|numeric|between:-90,90',
   'artisan_longitude' => 'required|numeric|between:-180,180',
   'supplier_latitude' => 'required|numeric|between:-90,90',
   'supplier_longitude' => 'required|numeric|between:-180,180',
  ];
 }

 /**
  * Get custom error messages for validation rules.
  */
 public function messages(): array
 {
  return [
   'jeton_code.required' => 'Le code du jeton est requis.',
   'jeton_code.string' => 'Le code du jeton doit être une chaîne de caractères.',
   'jeton_code.regex' => 'Le code du jeton doit être au format PA-XXXX.',
   'fournisseur_id.required' => 'L\'ID du fournisseur est requis.',
   'fournisseur_id.uuid' => 'L\'ID du fournisseur doit être un UUID valide.',
   'fournisseur_id.exists' => 'Le fournisseur spécifié n\'existe pas.',
   'amount_centimes.required' => 'Le montant est requis.',
   'amount_centimes.integer' => 'Le montant doit être un nombre entier.',
   'amount_centimes.min' => 'Le montant doit être d\'au moins 1 FCFA.',
   'artisan_latitude.required' => 'La latitude de l\'artisan est requise.',
   'artisan_latitude.numeric' => 'La latitude de l\'artisan doit être un nombre.',
   'artisan_latitude.between' => 'La latitude de l\'artisan doit être entre -90 et 90.',
   'artisan_longitude.required' => 'La longitude de l\'artisan est requise.',
   'artisan_longitude.numeric' => 'La longitude de l\'artisan doit être un nombre.',
   'artisan_longitude.between' => 'La longitude de l\'artisan doit être entre -180 et 180.',
   'supplier_latitude.required' => 'La latitude du fournisseur est requise.',
   'supplier_latitude.numeric' => 'La latitude du fournisseur doit êtreun nombre.',
   'supplier_latitude.between' => 'La latitude du fournisseur doit être entre -90 et 90.',
   'supplier_longitude.required' => 'La longitude du fournisseur est requise.',
   'supplier_longitude.numeric' => 'La longitude du fournisseur doit être un nombre.',
   'supplier_longitude.between' => 'La longitude du fournisseur doit être entre -180 et 180.',
  ];
 }
}
