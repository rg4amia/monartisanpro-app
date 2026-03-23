<?php

namespace App\Http\Requests\SupplierProduct;

class UpdateSupplierProductRequest extends StoreSupplierProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => $fieldRules) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
