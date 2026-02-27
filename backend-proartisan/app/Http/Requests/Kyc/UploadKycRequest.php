<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class UploadKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier est obligatoire.',
            'file.image'    => 'Le fichier doit être une image.',
            'file.mimes'    => 'Formats acceptés : JPEG, JPG, PNG.',
            'file.max'      => 'La taille du fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
