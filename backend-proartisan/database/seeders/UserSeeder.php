<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\Commune;
use App\Models\FournisseurAgree;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Numéros au format CI 10 chiffres : +225 + 10 chiffres
        // ── Admin ─────────────────────────────────────────────────────────────
        User::updateOrCreate(
            ['phone' => '+2250000000000'],
            [
                'email'      => 'admin@prosartisan.ci',
                'name'       => 'Administrateur ProsArtisan',
                'password'   => Hash::make('admin123'),
                'role'       => 'admin',
                'kyc_status' => 'actif',
            ]
        );

        // ── Client test ────────────────────────────────────────────────────────
        $plateau = Commune::where('slug', 'plateau')->first();
        $yopougon = Commune::where('slug', 'yopougon')->first();

        $client = User::updateOrCreate(
            ['phone' => '+2250100000001'],
            [
                'email'      => 'adjoua.kouassi@prosartisan.ci',
                'name'       => 'Adjoua Kouassi',
                'password'   => Hash::make('client123'),
                'role'       => 'client',
                'kyc_status' => 'actif',
                'commune_id' => $plateau?->id,
            ]
        );
        // Position Abidjan Plateau
        $client->setPosition(5.3543, -4.0083);

        // ── Artisans test ─────────────────────────────────────────────────────
        $electriciteSector = Sector::where('name', 'Électricité')->first();
        $electricienTrade  = Trade::where('name', 'Électricien bâtiment')->first();

        $artisan1 = User::updateOrCreate(
            ['phone' => '+2250200000001'],
            [
                'email'          => 'kouame.bah@prosartisan.ci',
                'name'           => 'Kouamé Bah',
                'password'       => Hash::make('artisan123'),
                'role'           => 'artisan',
                'kyc_status'     => 'actif',
                'score_nzassa'   => 78,
                'commune_id'     => $plateau?->id,
            ]
        );
        $artisan1->setPosition(5.3560, -4.0076);

        ArtisanProfile::updateOrCreate(
            ['user_id' => $artisan1->id],
            [
                'sector_id'        => $electriciteSector?->id,
                'trade_id'         => $electricienTrade?->id,
                'bio'              => 'Électricien certifié avec 8 ans d\'expérience en bâtiment résidentiel et commercial.',
                'experience_years' => 8,
            ]
        );

        $plomberieSector = Sector::where('name', 'Plomberie')->first();
        $plombierTrade   = Trade::where('name', 'Plombier sanitaire')->first();

        $artisan2 = User::updateOrCreate(
            ['phone' => '+2250200000002'],
            [
                'email'          => 'issa.traore@prosartisan.ci',
                'name'           => 'Issa Traoré',
                'password'       => Hash::make('artisan123'),
                'role'           => 'artisan',
                'kyc_status'     => 'actif',
                'score_nzassa'   => 65,
                'commune_id'     => $plateau?->id,
            ]
        );
        $artisan2->setPosition(5.3590, -4.0100);

        ArtisanProfile::updateOrCreate(
            ['user_id' => $artisan2->id],
            [
                'sector_id'        => $plomberieSector?->id,
                'trade_id'         => $plombierTrade?->id,
                'bio'              => 'Plombier sanitaire spécialisé dans les installations neuves et la rénovation.',
                'experience_years' => 5,
            ]
        );

        // ── Fournisseur test ──────────────────────────────────────────────────
        $fournisseur = User::updateOrCreate(
            ['phone' => '+2250300000001'],
            [
                'email'      => 'yao.koffi@prosartisan.ci',
                'name'       => 'Yao Koffi',
                'password'   => Hash::make('fourn123'),
                'role'       => 'fournisseur',
                'kyc_status' => 'actif',
                'commune_id' => $yopougon?->id,
            ]
        );

        $fournisseurAgree = FournisseurAgree::where('user_id', $fournisseur->id)->first();

        if ($fournisseurAgree) {
            $fournisseurAgree->update([
                'nom_boutique' => "Quincaillerie de l'Espoir",
                'statut'       => 'agree',
                'approuve_at'  => now(),
            ]);
        } else {
            if (config('database.default') === 'sqlite') {
                $fournisseurAgree = FournisseurAgree::create([
                    'user_id'      => $fournisseur->id,
                    'nom_boutique' => "Quincaillerie de l'Espoir",
                    'statut'       => 'agree',
                    'approuve_at'  => now(),
                    'position'     => '5.3300,-4.0620',
                ]);
            } else {
                DB::statement(
                    "INSERT INTO fournisseurs_agrees (user_id, nom_boutique, statut, approuve_at, position, created_at, updated_at)
                     VALUES (?, ?, 'agree', ?, POINT(?, ?), NOW(), NOW())",
                    [$fournisseur->id, "Quincaillerie de l'Espoir", now(), -4.0620, 5.3300]
                );

                $fournisseurAgree = FournisseurAgree::where('user_id', $fournisseur->id)->firstOrFail();
            }
        }

        $fournisseurAgree->setPosition(5.3300, -4.0620);

        // ── Référent de zone ───────────────────────────────────────────────────
        User::updateOrCreate(
            ['phone' => '+2250400000001'],
            [
                'email'      => 'seraphin.dioulo@prosartisan.ci',
                'name'       => 'Séraphin Dioulo',
                'password'   => Hash::make('referent123'),
                'role'       => 'referent',
                'kyc_status' => 'actif',
            ]
        );
    }
}
