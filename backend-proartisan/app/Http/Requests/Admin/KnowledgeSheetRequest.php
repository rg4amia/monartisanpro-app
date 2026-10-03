<?php

namespace App\Http\Requests\Admin;

use App\Services\Llm\KnowledgeSheetSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fiche technique de la base de connaissances saisie ou corrigée dans le backoffice.
 */
class KnowledgeSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return KnowledgeSheetSchema::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return KnowledgeSheetSchema::messages();
    }
}
