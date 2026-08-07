<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    /**
     * Obtenir la valeur typée du paramètre.
     */
    public function getTypedValue()
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Helper pour obtenir un paramètre par clé.
     */
    public static function getValueByKey(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->getTypedValue() : $default;
    }

    /**
     * Resolves the dynamic labor commission rate for an artisan based on their trade or sector.
     */
    public static function getLaborCommissionForArtisan(User $artisan): float
    {
        $globalCommission = (float) self::getValueByKey('commission_service', 0.10);
        $tradeName = $artisan->artisanProfile?->trade?->name;
        $sectorName = $artisan->artisanProfile?->sector?->name;
        
        if (!$tradeName && !$sectorName) {
            return $globalCommission;
        }

        $customCommissions = self::getValueByKey('commission_categories', []);
        if (!is_array($customCommissions)) {
            return $globalCommission;
        }

        $normalize = function($str) {
            $str = mb_strtolower($str, 'UTF-8');
            $str = str_replace(['é', 'è', 'ê', 'ë'], 'e', $str);
            $str = str_replace(['à', 'â', 'ä'], 'a', $str);
            $str = str_replace(['ç'], 'c', $str);
            $str = preg_replace('/[^a-z0-9_]/', '_', $str);
            return trim($str, '_');
        };

        $normTrade = $normalize($tradeName);
        $normSector = $normalize($sectorName);

        // 1. Check exact normalized trade name
        if (isset($customCommissions[$normTrade])) {
            return (float) $customCommissions[$normTrade];
        }

        // 2. Check exact normalized sector name
        if (isset($customCommissions[$normSector])) {
            return (float) $customCommissions[$normSector];
        }

        // 3. Substring matching (e.g. key "macon" matches "Maçon gros œuvre")
        foreach ($customCommissions as $key => $value) {
            $normKey = $normalize($key);
            if ($normKey && (str_contains($normTrade, $normKey) || str_contains($normSector, $normKey))) {
                return (float) $value;
            }
        }

        return $globalCommission;
    }
}
