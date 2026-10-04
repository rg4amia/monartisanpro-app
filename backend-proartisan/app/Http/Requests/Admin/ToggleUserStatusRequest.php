<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_status' => ['required', 'string', 'in:actif,suspendu'],
            'account_status_reason' => ['nullable', 'required_if:account_status,suspendu', 'string', 'min:5', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_status.required' => 'Le nouveau statut du compte est obligatoire.',
            'account_status.in' => 'Le statut du compte doit être « actif » ou « suspendu ».',
            'account_status_reason.required_if' => 'Indiquez le motif de la suspension.',
            'account_status_reason.min' => 'Le motif de la suspension doit contenir au moins 5 caractères.',
        ];
    }
}
