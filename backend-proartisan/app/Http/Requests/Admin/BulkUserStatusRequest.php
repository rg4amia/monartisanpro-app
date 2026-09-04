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
            'account_status_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Sélectionnez au moins un compte.',
            'user_ids.max' => 'Vous ne pouvez traiter que 100 comptes à la fois.',
            'account_status.in' => 'Le statut du compte doit être « actif » ou « suspendu ».',
        ];
    }
}
