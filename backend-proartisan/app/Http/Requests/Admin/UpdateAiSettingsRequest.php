<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_user_limit' => ['required', 'integer', 'min:0'],
            'ai_enabled' => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'daily_user_limit.required' => 'La limite journalière par utilisateur est obligatoire.',
            'daily_user_limit.min' => 'La limite journalière ne peut pas être négative.',
            'ai_enabled.required' => 'L’état d’activation de l’IA est obligatoire.',
        ];
    }
}
