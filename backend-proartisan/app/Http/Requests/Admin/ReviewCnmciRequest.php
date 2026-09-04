<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCnmciRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:valide,rejete'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être « valide » ou « rejete ».',
        ];
    }
}
