<?php

namespace App\Http\Requests\Devis;

use Illuminate\Foundation\Http\FormRequest;

class CreateDevisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lignes'                 => ['required', 'array', 'min:1'],
            'lignes.*.type'          => ['required', 'in:mo,mat'],
            'lignes.*.description'   => ['required', 'string', 'max:255'],
            'lignes.*.montant'       => ['required', 'integer', 'min:0'],
            'jalons'                 => ['required', 'array', 'min:1'],
            'jalons.*.ordre'         => ['required', 'integer', 'min:1'],
            'jalons.*.description'   => ['required', 'string', 'max:255'],
            'jalons.*.montant'       => ['required', 'integer', 'min:1000'],
            'jalons.*.date_cible'    => ['required', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'lignes.required'              => 'Les lignes du devis sont obligatoires.',
            'lignes.min'                   => 'Le devis doit comporter au moins une ligne.',
            'lignes.*.type.in'             => 'Le type doit être "mo" (main d\'œuvre) ou "mat" (matériaux).',
            'lignes.*.montant.min'         => 'Le montant d\'une ligne ne peut pas être négatif.',
            'jalons.required'              => 'Les jalons sont obligatoires.',
            'jalons.min'                   => 'Le devis doit comporter au moins un jalon.',
            'jalons.*.montant.min'         => 'Le montant d\'un jalon doit être d\'au moins 1 000 FCFA.',
            'jalons.*.date_cible.after'    => 'La date cible doit être dans le futur.',
        ];
    }
}
