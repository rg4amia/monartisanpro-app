<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadJCodePhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization dans le controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB max
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'La photo des matériaux est requise',
            'photo.image' => 'Le fichier doit être une image',
            'photo.mimes' => 'Format autorisé : JPEG, PNG ou WebP',
            'photo.max' => 'La photo ne doit pas dépasser 10 MB',
            'latitude.required' => 'La latitude GPS est requise',
            'latitude.between' => 'Latitude GPS invalide',
            'longitude.required' => 'La longitude GPS est requise',
            'longitude.between' => 'Longitude GPS invalide',
        ];
    }
}
