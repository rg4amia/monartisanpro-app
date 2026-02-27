<?php

namespace App\Http\Requests\JCode;

use Illuminate\Foundation\Http\FormRequest;

class GenerateJCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mission_id' => ['required', 'integer', 'exists:missions,id'],
            'montant'    => ['required', 'integer', 'min:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'La mission est obligatoire.',
            'mission_id.exists'   => 'Mission introuvable.',
            'montant.required'    => 'Le montant est obligatoire.',
            'montant.min'         => 'Le montant minimum est de 1 000 FCFA.',
        ];
    }
}
