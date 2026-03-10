<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommuneSeeder extends Seeder
{
    public function run(): void
    {
        // Communes du Grand Abidjan (District Autonome d'Abidjan).
        // Ordre: les 10 "historiques" puis les 3 communes périphériques.
        $names = [
            'Abobo',
            'Adjamé',
            'Attécoubé',
            'Cocody',
            'Koumassi',
            'Marcory',
            'Plateau',
            'Port-Bouët',
            'Treichville',
            'Yopougon',
            'Anyama',
            'Bingerville',
            'Songon',
        ];

        foreach ($names as $i => $name) {
            $slug = Str::slug($name);

            Commune::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'city' => 'Abidjan',
                    'country_code' => 'CI',
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}

