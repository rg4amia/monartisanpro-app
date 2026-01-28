<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for generating jeton
 *
 * Requirements: 5.1
 */
class GenerateJetonRequest extends FormRequest
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
            'sequestre_id' => 'required|uuid|exists:sequestres,id',
            'artisan_id' => 'required|uuid|exists:users,id',
            'supplier_ids' => 'sometimes|array',
            'supplier_ids.*' => 'uuid|exists:users,id',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'sequestre_id.required' => 'L\'ID du séquestre est requis.',
            'sequestre_id.uuid' => 'L\'ID du séquestre doit être un UUID valide.',
            'sequestre_id.exists' => 'Le séquestre spécifié n\'existe pas.',
            'artisan_id.required' => 'L\'ID de l\'artisan est requis.',
            'artisan_id.uuid' => 'L\'ID de l\'artisan doit être un UUID valide.',
            'artisan_id.exists' => 'L\'artisan spécifié n\'existe pas.',
            'supplier_ids.array' => 'Les IDs des fournisseurs doivent être un tableau.',
            'supplier_ids.*.uuid' => 'Chaque ID de fournisseur doit être un UUID valide.',
            'supplier_ids.*.exists' => 'Un ou plusieurs fournisseurs spécifiés n\'existent pas.',
        ];
    }
}
