<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for blocking escrow funds
 *
 * Requirements: 4.1, 4.2
 */
class BlockEscrowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mission_id' => 'required|uuid|exists:missions,id',
            'devis_id' => 'required|uuid|exists:devis,id',
            'client_id' => 'required|uuid|exists:users,id',
            'artisan_id' => 'required|uuid|exists:users,id',
            'total_amount_centimes' => 'required|integer|min:1000', // Minimum 10 XOF
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'mission_id.required' => 'L\'ID de la mission est requis.',
            'mission_id.uuid' => 'L\'ID de la mission doit être un UUID valide.',
            'mission_id.exists' => 'La mission spécifiée n\'existe pas.',
            'devis_id.required' => 'L\'ID du devis est requis.',
            'devis_id.uuid' => 'L\'ID du devis doit être un UUID valide.',
            'devis_id.exists' => 'Le devis spécifié n\'existe pas.',
            'client_id.required' => 'L\'ID du client est requis.',
            'client_id.uuid' => 'L\'ID du client doit être un UUID valide.',
            'client_id.exists' => 'Le client spécifié n\'existe pas.',
            'artisan_id.required' => 'L\'ID de l\'artisan est requis.',
            'artisan_id.uuid' => 'L\'ID de l\'artisan doit être un UUID valide.',
            'artisan_id.exists' => 'L\'artisan spécifié n\'existe pas.',
            'total_amount_centimes.required' => 'Le montant total est requis.',
            'total_amount_centimes.integer' => 'Le montant total doit être un nombre entier.',
            'total_amount_centimes.min' => 'Le montant total doit être d\'au moins 10 FCFA.',
        ];
    }
}
