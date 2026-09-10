<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiUserQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La route est gardée par `can:admin.ai.manage`.
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'monthly_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'blocked' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'blocked' => filter_var($this->input('blocked', false), FILTER_VALIDATE_BOOLEAN),
            'daily_limit' => $this->filled('daily_limit') ? $this->input('daily_limit') : null,
            'monthly_limit' => $this->filled('monthly_limit') ? $this->input('monthly_limit') : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'daily_limit.min' => 'La limite journalière ne peut pas être négative.',
            'monthly_limit.min' => 'La limite mensuelle ne peut pas être négative.',
            'note.max' => 'La note ne peut pas dépasser 500 caractères.',
        ];
    }
}
