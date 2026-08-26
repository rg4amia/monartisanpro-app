<?php

namespace App\Http\Requests\JCode;

use Illuminate\Foundation\Http\FormRequest;

class ScanJCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $rules = [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];

        if ($user && !$user->payment_phone) {
            $rules['payment_phone'] = ['required', 'string', 'max:20'];
            $rules['preferred_payment_provider'] = ['required', 'in:wave,orange_money'];
        } else {
            $rules['payment_phone'] = ['nullable', 'string', 'max:20'];
            $rules['preferred_payment_provider'] = ['nullable', 'in:wave,orange_money'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'La latitude GPS est obligatoire.',
            'lng.required' => 'La longitude GPS est obligatoire.',
            'lat.numeric'  => 'La latitude doit être un nombre.',
            'lng.numeric'  => 'La longitude doit être un nombre.',
            'lat.between'  => 'La latitude est invalide.',
            'lng.between'  => 'La longitude est invalide.',
        ];
    }
}
