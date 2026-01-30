<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for user registration
 *
 * Validates registration data for all user types:
 * - Client
 * - Artisan
 * - Fournisseur
 *
 * Requirements: 1.1, 1.2
 */
class RegisterRequest extends FormRequest
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
        $rules = [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'string', Rule::in(['CLIENT', 'ARTISAN', 'FOURNISSEUR'])],
            'phone_number' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
        ];

        // Additional validation for artisans
        if ($this->input('user_type') === 'ARTISAN') {
            $rules['trade_category'] = ['required', 'string', 'exists:trades,code'];
            $rules['location'] = ['nullable', 'array'];
            $rules['location.latitude'] = ['required_with:location', 'numeric', 'between:-90,90'];
            $rules['location.longitude'] = ['required_with:location', 'numeric', 'between:-180,180'];
            $rules['location.accuracy'] = ['nullable', 'numeric', 'min:0'];
        }

        // Additional validation for fournisseurs
        if ($this->input('user_type') === 'FOURNISSEUR') {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['shop_location'] = ['nullable', 'array'];
            $rules['shop_location.latitude'] = ['required_with:shop_location', 'numeric', 'between:-90,90'];
            $rules['shop_location.longitude'] = ['required_with:shop_location', 'numeric', 'between:-180,180'];
            $rules['shop_location.accuracy'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
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
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'user_type.required' => 'Le type d\'utilisateur est requis.',
            'user_type.in' => 'Le type d\'utilisateur doit être CLIENT, ARTISAN ou FOURNISSEUR.',
            'phone_number.required' => 'Le numéro de téléphone est requis.',
            'phone_number.regex' => 'Le numéro de téléphone doit être au format +225XXXXXXXXXX.',
            'trade_category.required' => 'La catégorie de métier est requise pour les artisans.',
            'trade_category.exists' => 'Le métier sélectionné n\'existe pas.',
            'location.required' => 'La localisation est requise pour les artisans.',
            'business_name.required' => 'Le nom de l\'entreprise est requis pour les fournisseurs.',
            'shop_location.required' => 'La localisation du magasin est requise pour les fournisseurs.',
        ];
    }
}
