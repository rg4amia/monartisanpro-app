<?php

namespace App\Http\Requests\Worksite;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for creating a new chantier
 *
 * Requirement 6.1: Validate chantier creation data
 */
class CreateChantierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'mission_id' => ['required', 'string', 'uuid'],
            'client_id' => ['required', 'string', 'uuid'],
            'artisan_id' => ['required', 'string', 'uuid'],
            'milestones' => ['sometimes', 'array'],
            'milestones.*.description' => ['required_with:milestones', 'string', 'max:1000'],
            'milestones.*.labor_amount_centimes' => ['required_with:milestones', 'integer', 'min:1'],
            'milestones.*.sequence_number' => ['required_with:milestones', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'L\'ID de la mission est requis',
            'mission_id.uuid' => 'L\'ID de la mission doit être un UUID valide',
            'client_id.required' => 'L\'ID du client est requis',
            'client_id.uuid' => 'L\'ID du client doit être un UUID valide',
            'artisan_id.required' => 'L\'ID de l\'artisan est requis',
            'artisan_id.uuid' => 'L\'ID de l\'artisan doit être un UUID valide',
            'milestones.array' => 'Les jalons doivent être un tableau',
            'milestones.*.description.required_with' => 'La description du jalon est requise',
            'milestones.*.description.max' => 'La description du jalon ne peut pas dépasser 1000 caractères',
            'milestones.*.labor_amount_centimes.required_with' => 'Le montant de main-d\'œuvre est requis',
            'milestones.*.labor_amount_centimes.integer' => 'Le montant de main-d\'œuvre doit être un entier',
            'milestones.*.labor_amount_centimes.min' => 'Le montant de main-d\'œuvre doit être positif',
            'milestones.*.sequence_number.required_with' => 'Le numéro de séquence est requis',
            'milestones.*.sequence_number.integer' => 'Le numéro de séquence doit être un entier',
            'milestones.*.sequence_number.min' => 'Le numéro de séquence doit être positif',
        ];
    }
}
