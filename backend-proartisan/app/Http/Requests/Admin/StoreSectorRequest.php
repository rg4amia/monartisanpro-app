<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sector = $this->route('sector');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('sectors', 'name')->ignore($sector?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique' => 'Cette catégorie existe déjà.',
            'name.max' => 'Le nom de la catégorie ne peut pas dépasser 100 caractères.',
        ];
    }
}
