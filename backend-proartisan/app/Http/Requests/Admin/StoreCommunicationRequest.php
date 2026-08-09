<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'    => 'required|string|in:annonce,le_saviez_vous',
            'titre'   => 'required|string|max:255',
            'contenu' => 'required|string',
            'cibles'  => 'required|array|min:1',
            'cibles.*' => 'required|string|in:client,artisan,fournisseur,livreur',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'    => 'Le type de communication est obligatoire.',
            'type.in'          => 'Le type doit être "annonce" ou "le_saviez_vous".',
            'titre.required'   => 'Le titre est obligatoire.',
            'titre.max'        => 'Le titre ne peut pas dépasser 255 caractères.',
            'contenu.required' => 'Le contenu est obligatoire.',
            'cibles.required'  => 'Vous devez sélectionner au moins un espace cible.',
            'cibles.min'       => 'Vous devez sélectionner au moins un espace cible.',
            'cibles.*.in'      => 'Chaque cible doit être : client, artisan, fournisseur ou livreur.',
        ];
    }
}
