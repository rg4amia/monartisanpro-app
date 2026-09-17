<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', 'string', 'in:client,artisan,fournisseur,referent,admin'],
            'password' => ['nullable', 'string', 'min:6'],
            'kyc_status' => ['required', 'string', 'in:en_attente,actif,rejete'],
            'account_status' => ['required', 'string', 'in:actif,suspendu'],
            'score_frozen' => ['nullable', 'boolean'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
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
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'role.required' => 'Le rôle est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
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
