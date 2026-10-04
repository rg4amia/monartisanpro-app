<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /** Format des numéros ivoiriens : `+225` suivi de dix chiffres. */
    public const PHONE_REGEX = '/^\+225[0-9]{10}$/';

    /** Rôles qu'un administrateur peut attribuer depuis le formulaire. */
    public const ROLES = 'client,artisan,fournisseur,livreur,referent,admin';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX, 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.self::ROLES],
            'password' => ['required', 'string', 'min:6'],
            'kyc_status' => ['required', 'string', 'in:en_attente,actif,rejete'],
            'score_frozen' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom complet est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex' => 'Le numéro doit commencer par +225 et compter dix chiffres.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Le rôle sélectionné est invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ];
    }
}
