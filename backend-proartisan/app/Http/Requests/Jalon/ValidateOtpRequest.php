<?php

namespace App\Http\Requests\Jalon;

use Illuminate\Foundation\Http\FormRequest;

class ValidateOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => 'Le code OTP est obligatoire.',
            'otp.digits'   => 'Le code OTP doit comporter exactement 4 chiffres.',
        ];
    }
}
