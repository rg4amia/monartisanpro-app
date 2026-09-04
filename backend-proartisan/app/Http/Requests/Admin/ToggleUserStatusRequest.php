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
            'account_status_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_status.required' => 'Le nouveau statut du compte est obligatoire.',
            'account_status.in' => 'Le statut du compte doit être « actif » ou « suspendu ».',
        ];
    }
}
