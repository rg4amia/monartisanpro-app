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
            'photos.*.photo' => 'required|file|mimetypes:image/jpeg,image/jpg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/3gpp,video/x-m4v|max:25600', // 25MB max
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
            'photos.required' => 'Au moins une preuve est requise',
            'photos.min' => 'Au moins une preuve est requise',
            'photos.max' => 'Maximum 5 preuves autorisées',
            'photos.*.photo.required' => 'Le fichier de preuve est requis',
            'photos.*.photo.file' => 'Le champ doit être un fichier valide',
            'photos.*.photo.mimetypes' => 'Format autorisé : JPEG, PNG, WebP, MP4, MOV, AVI, 3GP, M4V',
            'photos.*.photo.max' => 'Le fichier ne doit pas dépasser 25 MB',
            'photos.*.latitude.required' => 'La latitude GPS est requise',
            'photos.*.latitude.between' => 'Latitude GPS invalide',
            'photos.*.longitude.required' => 'La longitude GPS est requise',
            'photos.*.longitude.between' => 'Longitude GPS invalide',
        ];
    }
}
