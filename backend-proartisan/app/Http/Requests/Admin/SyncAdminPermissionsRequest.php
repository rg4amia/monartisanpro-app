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
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string', Rule::in($allowed)],
        ];
    }

    public function messages(): array
    {
        return [
            'capabilities.required' => AdminPermissionService::EMPTY_SELECTION_MESSAGE,
            'capabilities.min' => AdminPermissionService::EMPTY_SELECTION_MESSAGE,
            'capabilities.*.in' => 'Une des capacités transmises est inconnue.',
        ];
    }
}
