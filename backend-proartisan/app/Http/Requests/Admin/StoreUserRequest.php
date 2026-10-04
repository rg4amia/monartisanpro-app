<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /** Format des numéros ivoiriens : `+225` suivi de dix chiffres. */
    public const PHONE_REGEX = '/^\+225[0-9]{10}$/';

    /** Rôles qu'un administrateur peut attribuer depuis le formulaire. */
    public const ROLES = 'client,artisan,fournisseur,livreur,referent,admin';

    /** Longueur minimale du mot de passe : 12 caractères pour un administrateur, 8 sinon. */
    public static function passwordMinLength(?string $role): int
    {
        return $role === 'admin' ? 12 : 8;
    }

    public static function passwordMinMessage(?string $role): string
    {
        return $role === 'admin'
            ? 'Le mot de passe d’un administrateur doit contenir au moins 12 caractères.'
            : 'Le mot de passe doit contenir au moins 8 caractères.';
    }

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
            'password' => ['required', 'string', 'min:'.self::passwordMinLength($this->input('role'))],
            // Pris en compte pour un Référent ou un administrateur seulement :
            // les autres rôles passent par la revue KYC.
            'kyc_status' => ['nullable', 'string', 'in:en_attente,actif,rejete'],
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
            'password.min' => self::passwordMinMessage($this->input('role')),
        ];
    }
}
