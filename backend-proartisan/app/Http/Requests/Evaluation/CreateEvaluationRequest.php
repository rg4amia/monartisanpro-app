<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class CreateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mission_id'  => ['required', 'integer', 'exists:missions,id'],
            'evalue_id'   => ['required', 'integer', 'exists:users,id'],
            'note'        => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
            'fiabilite'   => ['nullable', 'integer', 'between:1,5'],
            'integrite'   => ['nullable', 'integer', 'between:1,5'],
            'qualite'     => ['nullable', 'integer', 'between:1,5'],
            'reactivite'  => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_id.required' => 'La mission est obligatoire.',
            'evalue_id.required'  => 'L\'utilisateur à évaluer est obligatoire.',
            'note.required'       => 'La note est obligatoire.',
            'note.between'        => 'La note doit être comprise entre 1 et 5 étoiles.',
        ];
    }
}
