<?php

namespace App\Http\Requests\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for mission creation
 *
 * Requirement 3.1: Mission creation with description, category, location, budget range
 */
class CreateMissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->user_type === 'CLIENT';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'category' => ['required', 'string'], // Category is now open-ended or derived from trade
            'trade_id' => ['nullable', 'exists:trades,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'budget_min_centimes' => ['required', 'integer', 'min:1000'], // Minimum 10 XOF
            'budget_max_centimes' => ['required', 'integer', 'min:1000', 'gte:budget_min_centimes'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'description.required' => 'La description de la mission est obligatoire',
            'description.min' => 'La description doit contenir au moins 10 caractères',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères',
            'category.required' => 'La catégorie de métier est obligatoire',
            'category.in' => 'La catégorie doit être : plombier, électricien ou maçon',
            'latitude.required' => 'La latitude est obligatoire',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90',
            'longitude.required' => 'La longitude est obligatoire',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180',
            'budget_min_centimes.required' => 'Le budget minimum est obligatoire',
            'budget_min_centimes.min' => 'Le budget minimum doit être d\'au moins 10 FCFA',
            'budget_max_centimes.required' => 'Le budget maximum est obligatoire',
            'budget_max_centimes.gte' => 'Le budget maximum doit être supérieur ou égal au budget minimum',
        ];
    }
}
