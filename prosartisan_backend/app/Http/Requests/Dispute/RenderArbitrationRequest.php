<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for rendering arbitration decisions
 *
 * Requirement 9.6: Validate arbitration decision data
 */
class RenderArbitrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'decision_type' => ['required', 'string', 'in:REFUND_CLIENT,PAY_ARTISAN,PARTIAL_REFUND,FREEZE_FUNDS'],
            'amount_centimes' => ['sometimes', 'integer', 'min:0'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_type.required' => 'Le type de décision est requis',
            'decision_type.in' => 'Le type de décision doit être: REFUND_CLIENT, PAY_ARTISAN, PARTIAL_REFUND ou FREEZE_FUNDS',
            'amount_centimes.integer' => 'Le montant doit être un nombre entier en centimes',
            'amount_centimes.min' => 'Le montant ne peut pas être négatif',
            'justification.required' => 'La justification est requise',
            'justification.min' => 'La justification doit contenir au moins 10 caractères',
            'justification.max' => 'La justification ne peut pas dépasser 2000 caractères',
        ];
    }
}
