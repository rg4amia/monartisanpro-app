<?php

namespace App\Http\Requests\Litige;

use Illuminate\Foundation\Http\FormRequest;

class StoreLitigeEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*.photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'photos.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'photos.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Au moins une preuve photo est requise.',
            'photos.array' => 'Les preuves doivent être envoyées sous forme de tableau.',
            'photos.min' => 'Au moins une preuve photo est requise.',
            'photos.max' => 'Vous pouvez envoyer au maximum 5 preuves à la fois.',
            'photos.*.photo.required' => 'Chaque preuve doit contenir une photo.',
            'photos.*.photo.image' => 'Chaque preuve doit être une image valide.',
            'photos.*.photo.mimes' => 'Les formats autorisés sont JPEG, PNG ou WebP.',
            'photos.*.photo.max' => 'Chaque photo ne doit pas dépasser 10 Mo.',
            'photos.*.latitude.required' => 'La latitude GPS est obligatoire pour chaque photo.',
            'photos.*.longitude.required' => 'La longitude GPS est obligatoire pour chaque photo.',
        ];
    }
}
