<?php

namespace App\Http\Requests\Admin;

use App\Services\BankTransferSettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Coordonnées de virement bancaire saisies dans le backoffice. L'IBAN est
 * contrôlé (format et clé ISO 13616) : une faute de frappe enverrait l'argent
 * des clients vers un compte erroné.
 */
class UpdateBankTransferSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iban' => BankTransferSettingsService::normalizeIban((string) $this->input('iban', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'iban' => [
                'required',
                'string',
                'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! self::hasValidChecksum((string) $value)) {
                        $fail("La clé de contrôle de l'IBAN est invalide : vérifiez sa saisie.");
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_name.required' => 'Le nom de la banque est obligatoire.',
            'account_name.required' => 'Le titulaire du compte est obligatoire.',
            'iban.required' => "L'IBAN est obligatoire.",
            'iban.regex' => "L'IBAN doit commencer par le code pays et la clé (ex. CI93 …) suivis de 10 à 30 caractères alphanumériques.",
        ];
    }

    /** Clé de contrôle ISO 13616 (modulo 97). */
    public static function hasValidChecksum(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
