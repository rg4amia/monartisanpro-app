<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesIvorianPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use NormalizesIvorianPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => $this->normalizeIvorianPhone((string) $this->phone)]);
        }

        if ($this->has('otpCode') && ! $this->has('otp')) {
            $this->merge(['otp' => $this->otpCode]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'otp' => ['required', 'string', 'digits:4'],
            'device_fingerprint' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex' => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'otp.required' => 'Le code OTP est obligatoire.',
            'otp.digits' => 'Le code OTP doit comporter exactement 4 chiffres.',
        ];
    }
}
