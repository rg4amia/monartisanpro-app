<?php

namespace App\Http\Requests\Devis;

use Illuminate\Foundation\Http\FormRequest;

use App\Rules\NoContactInformation;

class CreateDevisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'materials_required'     => ['nullable', 'boolean'],
            'intervention_type_id'   => ['nullable', 'integer', 'exists:intervention_types,id'],

            'lignes_json'            => ['sometimes', 'array', 'min:1'],
            'lignes_json.*.type'     => ['required_with:lignes_json', 'in:mo,mat'],
            'lignes_json.*.description' => ['required_with:lignes_json', 'string', 'max:255', new NoContactInformation()],
            'lignes_json.*.montant'  => ['required_with:lignes_json', 'integer', 'min:0'],
            'lignes_json.*.source'   => ['nullable', 'in:catalog,custom,artisan_stock'],
            'lignes_json.*.quantity' => ['nullable', 'integer', 'min:1'],
            'lignes_json.*.unit_price' => ['nullable', 'integer', 'min:1'],
            'lignes_json.*.sku'      => ['nullable', 'string', 'max:60'],
            'lignes_json.*.supplier_product_id' => ['nullable', 'integer', 'exists:supplier_products,id'],
            'lignes_json.*.artisan_stock_id' => ['nullable', 'integer', 'exists:artisan_stocks,id'],

            'lignes'                 => ['sometimes', 'array', 'min:1'],
            'lignes.*.type'          => ['required_with:lignes', 'in:mo,mat'],
            'lignes.*.description'   => ['required_with:lignes', 'string', 'max:255', new NoContactInformation()],
            'lignes.*.montant'       => ['required_with:lignes', 'integer', 'min:0'],
            'lignes.*.source'        => ['nullable', 'in:catalog,custom,artisan_stock'],
            'lignes.*.quantity'      => ['nullable', 'integer', 'min:1'],
            'lignes.*.unit_price'    => ['nullable', 'integer', 'min:1'],
            'lignes.*.sku'           => ['nullable', 'string', 'max:60'],
            'lignes.*.supplier_product_id' => ['nullable', 'integer', 'exists:supplier_products,id'],
            'lignes.*.artisan_stock_id' => ['nullable', 'integer', 'exists:artisan_stocks,id'],

            'jalons_json'            => ['sometimes', 'array', 'min:1'],
            'jalons_json.*.ordre'    => ['required_with:jalons_json', 'integer', 'min:1'],
            'jalons_json.*.description' => ['required_with:jalons_json', 'string', 'max:255', new NoContactInformation()],
            'jalons_json.*.montant'  => ['required_with:jalons_json', 'integer', 'min:1000'],
            'jalons_json.*.date_cible' => ['required_with:jalons_json', 'date', 'after:today'],

            'jalons'                 => ['sometimes', 'array', 'min:1'],
            'jalons.*.ordre'         => ['required_with:jalons', 'integer', 'min:1'],
            'jalons.*.description'   => ['required_with:jalons', 'string', 'max:255', new NoContactInformation()],
            'jalons.*.montant'       => ['required_with:jalons', 'integer', 'min:1000'],
            'jalons.*.date_cible'    => ['required_with:jalons', 'date', 'after:today'],
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
