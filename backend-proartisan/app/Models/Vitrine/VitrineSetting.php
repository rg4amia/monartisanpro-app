<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrineSetting extends Model
{
    protected $table = 'vitrine_settings';

    protected $fillable = [
        'cle',
        'valeur',
    ];

    /**
     * Récupère une valeur de paramètre par sa clé.
     */
    public static function get(string $cle, mixed $default = null): mixed
    {
        $setting = static::where('cle', $cle)->first();
        return $setting?->valeur ?? $default;
    }

    /**
     * Définit ou met à jour un paramètre.
     */
    public static function set(string $cle, string $valeur): static
    {
        return static::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => $valeur]
        );
    }

    /**
     * Retourne tous les paramètres sous forme de tableau clé-valeur.
     */
    public static function allAsArray(): array
    {
        return static::pluck('valeur', 'cle')->toArray();
    }
}
