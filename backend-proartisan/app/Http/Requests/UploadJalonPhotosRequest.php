<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadJalonPhotosRequest extends FormRequest
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
            'photos' => 'required|array|min:1|max:5',
            'photos.*.photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB max
            'photos.*.latitude' => 'required|numeric|between:-90,90',
            'photos.*.longitude' => 'required|numeric|between:-180,180',
            'photos.*.description' => 'nullable|string|max:500',
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
            'photos.required' => 'Au moins une photo est requise',
            'photos.min' => 'Au moins une photo est requise',
            'photos.max' => 'Maximum 5 photos autorisées',
            'photos.*.photo.required' => 'La photo est requise',
            'photos.*.photo.image' => 'Le fichier doit être une image',
            'photos.*.photo.mimes' => 'Format autorisé : JPEG, PNG ou WebP',
            'photos.*.photo.max' => 'La photo ne doit pas dépasser 10 MB',
            'photos.*.latitude.required' => 'La latitude GPS est requise',
            'photos.*.latitude.between' => 'Latitude GPS invalide',
            'photos.*.longitude.required' => 'La longitude GPS est requise',
            'photos.*.longitude.between' => 'Longitude GPS invalide',
        ];
    }
}
