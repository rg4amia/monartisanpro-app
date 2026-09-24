<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Coordonnées bancaires affichées aux payeurs qui choisissent le virement
 * (obligatoire au-delà du plafond Mobile Money). Renseignées depuis le
 * backoffice, jamais écrites dans le code : tant qu'elles manquent, le
 * virement est indisponible plutôt que dirigé vers un compte inventé
 * (Règle d'or 29).
 */
class BankTransferSettingsService
{
    public const GROUP = 'virement_bancaire';

    private const FIELDS = [
        'bank_name' => ['key' => 'virement_bank_name', 'label' => 'Banque'],
        'account_name' => ['key' => 'virement_account_name', 'label' => 'Titulaire du compte'],
        'iban' => ['key' => 'virement_iban', 'label' => 'IBAN'],
    ];

    /**
     * Coordonnées complètes, ou null si l'une d'elles manque.
     *
     * @return array{bank_name: string, account_name: string, iban: string}|null
     */
    public function details(): ?array
    {
        $values = $this->values();

        foreach ($values as $value) {
            if ($value === '') {
                return null;
            }
        }

        return $values;
    }

    /**
     * Valeurs actuelles (chaînes vides si non renseignées), pour le formulaire du backoffice.
     *
     * @return array{bank_name: string, account_name: string, iban: string}
     */
    public function values(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_column(self::FIELDS, 'key'))
            ->pluck('value', 'key');

        $values = [];
        foreach (self::FIELDS as $field => $meta) {
            $values[$field] = trim((string) $stored->get($meta['key'], ''));
        }

        return $values;
    }

    /**
     * @param  array{bank_name: string, account_name: string, iban: string}  $data  valeurs validées
     */
    public function update(array $data): void
    {
        foreach (self::FIELDS as $field => $meta) {
            $value = trim((string) $data[$field]);
            if ($field === 'iban') {
                $value = self::formatIban($value);
            }

            Setting::query()->updateOrCreate(
                ['key' => $meta['key']],
                ['value' => $value, 'type' => 'string', 'group' => self::GROUP, 'label' => $meta['label']],
            );
        }
    }

    /** IBAN compact en majuscules (« CI93 CI00 … » → « CI93CI00… »). */
    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    /** IBAN lisible, groupé par blocs de quatre caractères. */
    public static function formatIban(string $iban): string
    {
        return trim(chunk_split(self::normalizeIban($iban), 4, ' '));
    }
}
