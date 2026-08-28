<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $phone = preg_replace('/\s+/', '', (string) $this->phone);
            if (str_starts_with($phone, '00225')) {
                $phone = '+' . substr($phone, 2);
            } elseif (str_starts_with($phone, '225')) {
                $phone = '+' . $phone;
            } elseif (preg_match('/^[0-9]{10}$/', $phone)) {
                $phone = '+225' . $phone;
            }
            $this->merge(['phone' => $phone]);
        }

        if ($this->has('otpCode') && !$this->has('otp')) {
            $this->merge(['otp' => $this->otpCode]);
        }
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
