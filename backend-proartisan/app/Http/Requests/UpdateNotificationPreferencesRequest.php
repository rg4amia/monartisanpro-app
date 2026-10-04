<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Préférences de notification de l'utilisateur connecté (Chantier 14, lot E).
 * Les rubriques et ce qui peut y être coupé sont vérifiés par
 * `NotificationPreferenceService::update`.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'promotional_push' => ['sometimes', 'boolean'],
            'domains' => ['sometimes', 'array'],
            'domains.*' => ['array'],
            'domains.*.push' => ['sometimes', 'boolean'],
            'domains.*.sms' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'promotional_push.boolean' => 'Indiquez si vous acceptez les offres et nouveautés.',
            'domains.array' => 'Les préférences par rubrique sont invalides.',
            'domains.*.array' => 'Les préférences par rubrique sont invalides.',
            'domains.*.push.boolean' => 'Le réglage des notifications push est invalide.',
            'domains.*.sms.boolean' => 'Le réglage des SMS est invalide.',
        ];
    }
}
