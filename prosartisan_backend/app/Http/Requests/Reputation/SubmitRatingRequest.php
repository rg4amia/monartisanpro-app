<?php

namespace App\Http\Requests\Reputation;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRatingRequest extends FormRequest
{
 /**
  * Determine if the user is authorized to make this request.
  */
 public function authorize(): bool
 {
  return true; // Authorization handled in controller
 }

 /**
  * Get the validation rules that apply to the request.
  *
  * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
  */
 public function rules(): array
 {
  return [
   'rating' => ['required', 'integer', 'min:1', 'max:5'],
   'comment' => ['nullable', 'string', 'max:1000'],
  ];
 }

 /**
  * Get custom messages for validator errors.
  */
 public function messages(): array
 {
  return [
   'rating.required' => 'La note est obligatoire.',
   'rating.integer' => 'La note doit être un nombre entier.',
   'rating.min' => 'La note doit être au minimum de 1 étoile.',
   'rating.max' => 'La note doit être au maximum de 5 étoiles.',
   'comment.string' => 'Le commentaire doit être du texte.',
   'comment.max' => 'Le commentaire ne peut pas dépasser 1000 caractères.',
  ];
 }
}
