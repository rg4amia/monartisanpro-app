<?php

namespace App\Http\Requests\Jalon;

use Illuminate\Foundation\Http\FormRequest;

class SubmitJalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos'            => ['nullable', 'array'],
            'photos.*.url'      => ['required_with:photos', 'string'],
            'photos.*.lat'      => ['required_with:photos', 'numeric'],
            'photos.*.lng'      => ['required_with:photos', 'numeric'],
            'photos.*.taken_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.*.url.required_with' => 'L\'URL de la photo est obligatoire.',
            'photos.*.lat.numeric'       => 'La latitude doit être un nombre.',
            'photos.*.lng.numeric'       => 'La longitude doit être un nombre.',
        ];
    }
}
