<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approuve,rejete'],
            'rejection_reason' => ['nullable', 'string', 'min:10', 'max:1000', 'required_if:decision,rejete'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être approuve ou rejete.',
            'rejection_reason.required_if' => 'Le motif de rejet est obligatoire si la décision est rejetée.',
            'rejection_reason.min' => 'Le motif de rejet doit contenir au moins 10 caractères.',
        ];
    }
}
