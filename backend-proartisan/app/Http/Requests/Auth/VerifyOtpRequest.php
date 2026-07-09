<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'otp'   => ['required', 'string', 'digits:4'],
            'device_fingerprint' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'    => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'otp.required'   => 'Le code OTP est obligatoire.',
            'otp.digits'     => 'Le code OTP doit comporter exactement 4 chiffres.',
        ];
    }
}
