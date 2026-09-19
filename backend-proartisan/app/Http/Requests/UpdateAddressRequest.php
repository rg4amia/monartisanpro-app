<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization dans le controller (propriétaire = utilisateur connecté)
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'sometimes|required|string|max:150',
            'recipient_phone' => ['sometimes', 'required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'address_line' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Le nom du destinataire est requis',
            'recipient_phone.required' => 'Le numéro de téléphone du destinataire est requis',
            'recipient_phone.regex' => 'Le numéro doit être au format +225XXXXXXXXXX',
            'address_line.required' => 'L\'adresse est requise',
            'city.required' => 'La ville est requise',
        ];
    }
}
