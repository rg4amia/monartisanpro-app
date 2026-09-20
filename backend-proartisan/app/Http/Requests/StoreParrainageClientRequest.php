<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesIvorianPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreParrainageClientRequest extends FormRequest
{
    use NormalizesIvorianPhone;

    public function authorize(): bool
    {
        // L'autorisation métier (rôle du parrain...) est vérifiée dans
        // ParrainageClientService, pas ici.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('filleul_phone')) {
            $this->merge(['filleul_phone' => $this->normalizeIvorianPhone((string) $this->filleul_phone)]);
        }
    }

    public function rules(): array
    {
        return [
            'filleul_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'filleul_nom' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'filleul_phone.required' => 'Le numéro de téléphone du filleul est obligatoire.',
            'filleul_phone.regex' => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'filleul_nom.required' => 'Le nom du filleul est obligatoire.',
            'filleul_nom.max' => 'Le nom du filleul ne doit pas dépasser 150 caractères.',
        ];
    }
}
