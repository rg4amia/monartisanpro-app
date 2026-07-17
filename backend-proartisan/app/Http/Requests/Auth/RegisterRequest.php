<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^\+225[0-9]{10}$/',
                function ($attribute, $value, $fail) {
                    $existingUser = \App\Models\User::where('phone', $value)->first();
                    if ($existingUser && $existingUser->name !== null && $existingUser->role !== null) {
                        $fail('Ce numéro de téléphone est déjà associé à un compte.');
                    }
                }
            ],
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'role'  => ['required', 'string', 'in:client,artisan,fournisseur'],
            'device_fingerprint' => ['nullable', 'string'],
            'sector_id' => ['sometimes', 'nullable', 'exists:sectors,id'],
            'trade_id' => [
                'sometimes', 'nullable', 'exists:trades,id',
                function ($attribute, $value, $fail) {
                    $sectorId = $this->input('sector_id');
                    if ($value && $sectorId) {
                        $exists = \DB::table('trades')
                            ->where('id', $value)
                            ->where('sector_id', $sectorId)
                            ->exists();
                        if (!$exists) {
                            $fail('Le métier sélectionné doit appartenir au secteur d\'activité choisi.');
                        }
                    }
                }
            ],
            'bio' => ['sometimes', 'nullable', 'string'],
            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'cgu_accepted' => ['required', 'accepted', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'    => 'Le numéro doit être au format +225XXXXXXXXXX.',
            'name.required'  => 'Le nom complet est obligatoire.',
            'name.min'       => 'Le nom doit comporter au moins 2 caractères.',
            'role.required'  => 'Le rôle est obligatoire.',
            'role.in'        => 'Le rôle doit être client, artisan ou fournisseur.',
            'cgu_accepted.required' => 'Vous devez accepter les conditions générales d\'utilisation.',
            'cgu_accepted.accepted' => 'Vous devez accepter les conditions générales d\'utilisation.',
        ];
    }
}
