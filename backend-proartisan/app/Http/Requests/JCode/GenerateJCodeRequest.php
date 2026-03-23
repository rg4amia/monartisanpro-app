<?php

namespace App\Http\Requests\JCode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'fournisseur_id' => ['required', 'integer', 'exists:users,id'],
            'montant'    => ['nullable', 'integer', 'min:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_product_id' => ['nullable', 'integer', 'exists:supplier_products,id'],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.sku' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'La mission est obligatoire.',
            'mission_id.exists'   => 'Mission introuvable.',
            'montant.min'         => 'Le montant minimum est de 1 000 FCFA.',
            'fournisseur_id.required' => 'Le fournisseur est obligatoire.',
            'fournisseur_id.exists' => 'Fournisseur introuvable.',
            'items.required' => 'Ajoutez au moins un article à la demande.',
            'items.min' => 'Ajoutez au moins un article à la demande.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque article.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $productId = $item['supplier_product_id'] ?? null;
                $name = trim((string) ($item['name'] ?? ''));
                $unitPrice = $item['unit_price'] ?? null;

                if (! $productId && $name === '') {
                    $validator->errors()->add(
                        "items.$index.name",
                        'Ajoutez un article du catalogue ou renseignez un nom pour l\'article personnalisé.'
                    );
                }

                if (! $productId && ($unitPrice === null || (int) $unitPrice <= 0)) {
                    $validator->errors()->add(
                        "items.$index.unit_price",
                        'Le prix unitaire est obligatoire pour un article personnalisé.'
                    );
                }
            }
        });
    }
}
