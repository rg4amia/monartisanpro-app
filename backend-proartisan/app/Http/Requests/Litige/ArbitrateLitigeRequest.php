<?php

namespace App\Http\Requests\Litige;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArbitrateLitigeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['client', 'artisan', 'mixte', 'gel'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_materiaux' => ['nullable', 'integer', 'min:0'],
            'refund_mo' => ['nullable', 'integer', 'min:0'],
            'release_materiaux' => ['nullable', 'integer', 'min:0'],
            'release_mo' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être client, artisan, mixte ou gel.',
            'notes.max' => 'Les notes administrateur ne peuvent pas dépasser 2 000 caractères.',
            'refund_materiaux.integer' => 'Le remboursement matériaux doit être un entier FCFA.',
            'refund_mo.integer' => 'Le remboursement main d\'œuvre doit être un entier FCFA.',
            'release_materiaux.integer' => 'La libération matériaux doit être un entier FCFA.',
            'release_mo.integer' => 'La libération main d\'œuvre doit être un entier FCFA.',
        ];
    }
}
