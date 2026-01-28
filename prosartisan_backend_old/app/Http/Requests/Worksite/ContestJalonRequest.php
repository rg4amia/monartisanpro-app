<?php

namespace App\Http\Requests\Worksite;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for contesting a jalon
 *
 * Requirement 6.3: Validate jalon contestation request
 */
class ContestJalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'La raison de la contestation est requise',
            'reason.string' => 'La raison doit être une chaîne de caractères',
            'reason.min' => 'La raison doit contenir au moins 10 caractères',
            'reason.max' => 'La raison ne peut pas dépasser 1000 caractères',
        ];
    }
}
