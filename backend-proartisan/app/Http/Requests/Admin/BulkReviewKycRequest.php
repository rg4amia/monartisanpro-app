<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revue KYC groupée depuis le backoffice (Chantier C5 / P1-9).
 */
class BulkReviewKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'decision' => ['required', 'in:approuve,rejete'],
            'rejection_reason' => ['nullable', 'string', 'min:10', 'max:1000', 'required_if:decision,rejete'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Sélectionnez au moins un dossier.',
            'user_ids.max' => 'Vous ne pouvez traiter que 100 dossiers à la fois.',
            'decision.in' => 'La décision doit être approuve ou rejete.',
            'rejection_reason.required_if' => 'Le motif de rejet est obligatoire si la décision est rejetée.',
            'rejection_reason.min' => 'Le motif de rejet doit contenir au moins 10 caractères.',
        ];
    }
}
