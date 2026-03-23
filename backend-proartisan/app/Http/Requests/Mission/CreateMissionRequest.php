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
            'artisan_id'   => ['nullable', 'integer', 'exists:users,id'],
            'sector_id'    => ['nullable', 'integer', 'exists:sectors,id'],
            'trade_id'     => ['nullable', 'integer', 'exists:trades,id'],
            'category'     => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'photos'      => ['nullable', 'array', 'max:5'],
            'photos.*'    => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'artisan_id.exists'    => 'L\'artisan sélectionné est introuvable.',
            'sector_id.exists'     => 'Le secteur sélectionné est invalide.',
            'trade_id.exists'      => 'Le métier sélectionné est invalide.',
            'category.max'         => 'La catégorie est trop longue.',
            'description.required' => 'La description de votre besoin est obligatoire.',
            'description.min'      => 'La description doit comporter au moins 20 caractères.',
            'lat.numeric'          => 'La latitude doit être un nombre.',
            'lng.numeric'          => 'La longitude doit être un nombre.',
            'location_address.max' => 'L\'adresse est trop longue.',
        ];
    }
}
