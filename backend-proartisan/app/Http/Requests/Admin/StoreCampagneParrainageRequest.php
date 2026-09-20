<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampagneParrainageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($this->input('discount_type') === 'percent' && $value > 100) {
                        $fail('Le pourcentage de remise ne peut pas dépasser 100.');
                    }
                },
            ],
            'max_discount_amount' => ['nullable', 'integer', 'min:0'],
            'min_montant' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.required' => 'Le libellé est obligatoire.',
            'discount_type.required' => 'Le type de remise est obligatoire.',
            'discount_type.in' => 'Le type de remise doit être « percent » ou « fixed ».',
            'discount_value.required' => 'La valeur de la remise est obligatoire.',
            'discount_value.min' => 'La valeur de la remise doit être au moins 1.',
        ];
    }
}
