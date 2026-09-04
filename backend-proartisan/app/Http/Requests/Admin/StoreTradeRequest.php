<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'exists:sectors,id'],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'sector_id.required' => 'La catégorie parente est obligatoire.',
            'sector_id.exists' => 'La catégorie parente sélectionnée n’existe pas.',
            'name.required' => 'Le nom de la sous-catégorie est obligatoire.',
            'name.max' => 'Le nom de la sous-catégorie ne peut pas dépasser 100 caractères.',
        ];
    }
}
