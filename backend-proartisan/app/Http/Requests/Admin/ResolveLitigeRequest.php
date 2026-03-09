<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResolveLitigeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:client,artisan,gel'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être client, artisan ou gel.',
        ];
    }
}
