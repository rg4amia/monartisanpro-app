<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $code = $this->route('promoCode');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($code?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_discount_amount' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code est obligatoire.',
            'code.unique' => 'Ce code promo existe déjà.',
            'discount_type.required' => 'Le type de remise est obligatoire.',
            'discount_type.in' => 'Le type de remise doit être « percent » ou « fixed ».',
            'discount_value.required' => 'La valeur de la remise est obligatoire.',
            'discount_value.min' => 'La valeur de la remise doit être au moins 1.',
        ];
    }
}
