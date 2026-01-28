<?php

namespace App\Http\Requests\Marketplace;

use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for quote creation
 *
 * Requirement 3.3: Devis with itemized costs for materials and labor
 */
class CreateQuoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->user_type === 'ARTISAN';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'line_items' => ['required', 'array', 'min:1', 'max:20'],
            'line_items.*.description' => ['required', 'string', 'min:3', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'line_items.*.unit_price_centimes' => ['required', 'integer', 'min:1'],
            'line_items.*.type' => ['required', 'string', Rule::in([
                DevisLineType::MATERIAL,
                DevisLineType::LABOR,
            ])],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'line_items.required' => 'Au moins un élément de devis est requis',
            'line_items.min' => 'Le devis doit contenir au moins un élément',
            'line_items.max' => 'Le devis ne peut pas contenir plus de 20 éléments',
            'line_items.*.description.required' => 'La description de l\'élément est obligatoire',
            'line_items.*.description.min' => 'La description doit contenir au moins 3 caractères',
            'line_items.*.description.max' => 'La description ne peut pas dépasser 255 caractères',
            'line_items.*.quantity.required' => 'La quantité est obligatoire',
            'line_items.*.quantity.min' => 'La quantité doit être d\'au moins 1',
            'line_items.*.quantity.max' => 'La quantité ne peut pas dépasser 1000',
            'line_items.*.unit_price_centimes.required' => 'Le prix unitaire est obligatoire',
            'line_items.*.unit_price_centimes.min' => 'Le prix unitaire doit être positif',
            'line_items.*.type.required' => 'Le type d\'élément est obligatoire',
            'line_items.*.type.in' => 'Le type doit être MATERIAL ou LABOR',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure line_items is an array
        if (! is_array($this->line_items)) {
            $this->merge(['line_items' => []]);
        }
    }
}
