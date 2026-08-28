<?php

namespace App\Http\Requests\SupplierProduct;

use Illuminate\Foundation\Http\FormRequest;

use App\Rules\NoContactInformation;

class StoreSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('unitPrice') && !$this->has('unit_price')) {
            $merge['unit_price'] = $this->unitPrice;
        }
        if ($this->has('stockQuantity') && !$this->has('stock_quantity')) {
            $merge['stock_quantity'] = $this->stockQuantity;
        }
        if ($this->has('imageUrl') && !$this->has('image_url')) {
            $merge['image_url'] = $this->imageUrl;
        }
        if ($this->has('isActive') && !$this->has('is_active')) {
            $merge['is_active'] = $this->isActive;
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150', new NoContactInformation()],
            'sku' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000', new NoContactInformation()],
            'unit_price' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de l\'article est obligatoire.',
            'unit_price.required' => 'Le prix unitaire est obligatoire.',
            'unit_price.integer' => 'Le prix unitaire doit être un entier en FCFA.',
            'stock_quantity.required' => 'Le stock est obligatoire.',
            'stock_quantity.integer' => 'Le stock doit être un nombre entier.',
        ];
    }
}
