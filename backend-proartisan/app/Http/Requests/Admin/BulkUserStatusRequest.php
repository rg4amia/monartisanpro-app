<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement de statut de compte groupé depuis le backoffice (Chantier C5 / P1-9).
 */
class BulkUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'account_status' => ['required', 'string', 'in:actif,suspendu'],
            'account_status_reason' => ['nullable', 'required_if:account_status,suspendu', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Sélectionnez au moins un compte.',
            'user_ids.max' => 'Vous ne pouvez traiter que 100 comptes à la fois.',
            'account_status.in' => 'Le statut du compte doit être « actif » ou « suspendu ».',
            'account_status_reason.required_if' => 'Indiquez le motif de la suspension.',
            'account_status_reason.min' => 'Le motif de la suspension doit contenir au moins 5 caractères.',
        ];
    }
}
