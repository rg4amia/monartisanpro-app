<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:client,artisan,fournisseur,referent,admin'],
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Le rôle est requis.',
            'role.in' => 'Le rôle spécifié est invalide.',
            'permission.required' => 'La permission est requise.',
            'permission.exists' => 'La permission spécifiée n\'existe pas.',
        ];
    }
}
