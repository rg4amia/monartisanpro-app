<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminPermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Affectation des capacités fines du backoffice à un compte admin (Chantier C6 / P2-10).
 */
class SyncAdminPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = array_merge(
            AdminPermissionService::allCapabilityNames(),
            [AdminPermissionService::FULL_ACCESS],
        );

        return [
            'capabilities' => ['present', 'array'],
            'capabilities.*' => ['string', Rule::in($allowed)],
        ];
    }

    public function messages(): array
    {
        return [
            'capabilities.present' => 'La liste des capacités est requise (même vide).',
            'capabilities.*.in' => 'Une des capacités transmises est inconnue.',
        ];
    }
}
