<?php

namespace App\Http\Requests\Worksite;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for submitting jalon proof
 *
 * Requirement 6.2: Validate GPS-tagged photo proof submission
 */
class SubmitProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'max:10240'], // Max 10MB
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'captured_at' => ['sometimes', 'date'],
            'exif_data' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'La photo est requise',
            'photo.file' => 'Le fichier photo est invalide',
            'photo.image' => 'Le fichier doit être une image',
            'photo.max' => 'La photo ne peut pas dépasser 10MB',
            'latitude.required' => 'La latitude GPS est requise',
            'latitude.numeric' => 'La latitude doit être un nombre',
            'latitude.between' => 'La latitude doit être entre -90 et 90',
            'longitude.required' => 'La longitude GPS est requise',
            'longitude.numeric' => 'La longitude doit être un nombre',
            'longitude.between' => 'La longitude doit être entre -180 et 180',
            'accuracy.numeric' => 'La précision GPS doit être un nombre',
            'accuracy.min' => 'La précision GPS doit être positive',
            'accuracy.max' => 'La précision GPS ne peut pas dépasser 1000m',
            'captured_at.date' => 'La date de capture doit être une date valide',
            'exif_data.array' => 'Les données EXIF doivent être un tableau',
        ];
    }

    /**
     * Custom validation to ensure GPS accuracy is acceptable
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $accuracy = $this->input('accuracy', 10.0);

            // Requirement 10.4: GPS accuracy must be < 10m for validation
            if ($accuracy > 10.0) {
                $validator->errors()->add(
                    'accuracy',
                    'La précision GPS doit être inférieure à 10 mètres pour la validation'
                );
            }
        });
    }
}
