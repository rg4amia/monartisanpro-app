<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewFournisseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:agree,suspendu'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être agree ou suspendu.',
        ];
    }
}
