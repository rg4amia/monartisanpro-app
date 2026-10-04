<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le statut du compte et l'empreinte de l'appareil ne se modifient pas par ce
 * formulaire : le statut passe par le changement de statut (motif, date,
 * audit), l'empreinte est relevée par l'application.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        // Un numéro enregistré hors format reste accepté tant qu'il n'est pas
        // modifié : le compte doit rester corrigeable sur ses autres champs.
        $phoneRules = ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)];
        if ($this->input('phone') !== $user->phone) {
            $phoneRules[] = 'regex:'.StoreUserRequest::PHONE_REGEX;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => $phoneRules,
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', 'in:'.StoreUserRequest::ROLES],
            'password' => ['nullable', 'string', 'min:'.StoreUserRequest::passwordMinLength($this->input('role'))],
            'kyc_status' => ['nullable', 'string', 'in:en_attente,actif,rejete'],
            'fournisseur_shop_name' => ['nullable', 'string', 'min:2', 'max:150'],
            'score_frozen' => ['nullable', 'boolean'],
            'fournisseur_sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'fournisseur_trade_id' => ['nullable', 'integer', 'exists:trades,id'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
            'documents.cni' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
            'documents.selfie' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
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
            'password.min' => StoreUserRequest::passwordMinMessage($this->input('role')),
            'fournisseur_shop_name.min' => 'Le nom de la boutique doit contenir au moins 2 caractères.',
            'fournisseur_shop_name.max' => 'Le nom de la boutique ne doit pas dépasser 150 caractères.',
            'fournisseur_sector_id.exists' => 'Le secteur d’activité sélectionné est invalide.',
            'fournisseur_trade_id.exists' => 'La sous-catégorie (métier) sélectionnée est invalide.',
            'photo.image' => 'La photo doit être une image.',
            'photo.mimes' => 'Formats acceptés pour la photo : JPEG, JPG, PNG.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'documents.cni.image' => 'La pièce CNI doit être une image.',
            'documents.cni.mimes' => 'Formats acceptés pour la CNI : JPEG, JPG, PNG.',
            'documents.cni.max' => 'La pièce CNI ne doit pas dépasser 5 Mo.',
            'documents.selfie.image' => 'Le selfie doit être une image.',
            'documents.selfie.mimes' => 'Formats acceptés pour le selfie : JPEG, JPG, PNG.',
            'documents.selfie.max' => 'Le selfie ne doit pas dépasser 5 Mo.',
        ];
    }
}
