<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'phone'   => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'channel' => ['nullable', 'string', 'in:sms,whatsapp'],
            'role'    => ['nullable', 'string', 'in:client,artisan,fournisseur,driver,referent,LIVREUR,CLIENT,ARTISAN,FOURNISSEUR'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'    => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'channel.in'     => 'Le canal de communication doit être "sms" ou "whatsapp".',
        ];
    }
}
