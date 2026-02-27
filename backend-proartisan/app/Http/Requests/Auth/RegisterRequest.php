<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'role'  => ['required', 'string', 'in:client,artisan,fournisseur'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'    => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'name.required'  => 'Le nom complet est obligatoire.',
            'name.min'       => 'Le nom doit comporter au moins 2 caractères.',
            'role.required'  => 'Le rôle est obligatoire.',
            'role.in'        => 'Le rôle doit être client, artisan ou fournisseur.',
        ];
    }
}
