<?php

namespace App\Http\Requests\Mission;

use Illuminate\Foundation\Http\FormRequest;

class CreateMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'photos'      => ['nullable', 'array', 'max:5'],
            'photos.*'    => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La description de votre besoin est obligatoire.',
            'description.min'      => 'La description doit comporter au moins 20 caractères.',
            'lat.numeric'          => 'La latitude doit être un nombre.',
            'lng.numeric'          => 'La longitude doit être un nombre.',
        ];
    }
}
