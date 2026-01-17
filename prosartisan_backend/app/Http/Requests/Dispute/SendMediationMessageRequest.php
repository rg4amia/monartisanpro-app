<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for sending mediation messages
 *
 * Requirement 9.5: Validate mediation communication data
 */
class SendMediationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Le message est requis',
            'message.string' => 'Le message doit être une chaîne de caractères',
            'message.min' => 'Le message ne peut pas être vide',
            'message.max' => 'Le message ne peut pas dépasser 1000 caractères',
        ];
    }
}
