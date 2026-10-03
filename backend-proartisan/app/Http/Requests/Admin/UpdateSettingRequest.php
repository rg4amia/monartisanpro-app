<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Bornes des réglages dont une valeur aberrante aurait un effet financier
     * ou bloquerait un parcours (Chantier 19).
     *
     * @var array<string, list<string>>
     */
    private const BOUNDED = [
        'mission_cancellation_penalty_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        'mission_artisan_response_hours' => ['required', 'integer', 'min:1', 'max:720'],
        'mission_final_approval_hours' => ['required', 'integer', 'min:1', 'max:720'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $setting = $this->route('setting');
        $key = $setting instanceof Setting ? $setting->key : null;

        return [
            'value' => self::BOUNDED[$key] ?? ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Une valeur est obligatoire pour ce réglage.',
            'value.numeric' => 'La valeur doit être un nombre.',
            'value.integer' => 'La valeur doit être un nombre entier.',
            'value.min' => 'La valeur doit être au moins :min.',
            'value.max' => 'La valeur ne peut pas dépasser :max.',
        ];
    }
}
