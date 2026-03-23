<?php

namespace App\Http\Requests\Litige;

use Illuminate\Foundation\Http\FormRequest;

class CreateLitigeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mission_id' => ['required', 'integer', 'exists:missions,id'],
            'motif' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'La mission est obligatoire.',
            'mission_id.exists' => 'La mission sélectionnée est introuvable.',
            'motif.max' => 'Le motif ne peut pas dépasser 120 caractères.',
            'description.required' => 'La description du litige est obligatoire.',
            'description.min' => 'La description doit comporter au moins 20 caractères.',
            'description.max' => 'La description ne peut pas dépasser 2 000 caractères.',
        ];
    }
}
