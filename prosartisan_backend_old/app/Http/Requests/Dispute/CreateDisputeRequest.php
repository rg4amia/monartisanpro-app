<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for creating a dispute
 *
 * Requirement 9.1: Validate dispute creation data
 */
class CreateDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'mission_id' => ['required', 'uuid', 'exists:missions,id'],
            'defendant_id' => ['required', 'uuid', 'exists:users,id'],
            'type' => ['required', 'string', 'in:QUALITY,PAYMENT,DELAY,OTHER'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence' => ['sometimes', 'array'],
            'evidence.*' => ['url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'L\'IDde la mission est requis',
            'mission_id.uuid' => 'L\'ID de la mission doit être un UUID valide',
            'mission_id.exists' => 'La mission spécifiée n\'existe pas',
            'defendant_id.required' => 'L\'ID du défendeur est requis',
            'defendant_id.uuid' => 'L\'ID du défendeur doit être un UUID valide',
            'defendant_id.exists' => 'L\'utilisateur défendeur n\'existe pas',
            'type.required' => 'Le type de litige est requis',
            'type.in' => 'Le type de litige doit être: QUALITY, PAYMENT, DELAY ou OTHER',
            'description.required' => 'La description du litige est requise',
            'description.min' => 'La description doit contenir au moins 10 caractères',
            'description.max' => 'La description ne peut pas dépasser 2000 caractères',
            'evidence.array' => 'Les preuves doivent être un tableau d\'URLs',
            'evidence.*.url' => 'Chaque preuve doit être une URL valide',
            'evidence.*.max' => 'Chaque URL de preuve ne peut pas dépasser 500 caractères',
        ];
    }
}
