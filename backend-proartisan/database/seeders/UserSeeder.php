<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\Commune;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Numéros au format CI 10 chiffres : +225 + 10 chiffres
        // ── Admin ─────────────────────────────────────────────────────────────
        User::create([
            'phone'      => '+2250000000000',
            'name'       => 'Administrateur ProsArtisan',
            'password'   => bcrypt('admin123'),
            'role'       => 'admin',
            'kyc_status' => 'actif',
        ]);

        // ── Client test ────────────────────────────────────────────────────────
        $plateau = Commune::where('slug', 'plateau')->first();
        $yopougon = Commune::where('slug', 'yopougon')->first();

        $client = User::create([
            'phone'      => '+2250100000001',
            'name'       => 'Adjoua Kouassi',
            'password'   => bcrypt('client123'),
            'role'       => 'client',
            'kyc_status' => 'actif',
            'commune_id' => $plateau?->id,
        ]);
        // Position Abidjan Plateau
        $client->setPosition(5.3543, -4.0083);

        // ── Artisans test ─────────────────────────────────────────────────────
        $electriciteSector = Sector::where('name', 'Électricité')->first();
        $electricienTrade  = Trade::where('name', 'Électricien bâtiment')->first();

        $artisan1 = User::create([
            'phone'          => '+2250200000001',
            'name'           => 'Kouamé Bah',
            'password'       => bcrypt('artisan123'),
            'role'           => 'artisan',
            'kyc_status'     => 'actif',
            'score_nzassa'   => 78,
            'commune_id'     => $plateau?->id,
        ]);
        $artisan1->setPosition(5.3560, -4.0076);

        ArtisanProfile::create([
            'user_id'          => $artisan1->id,
            'sector_id'        => $electriciteSector?->id,
            'trade_id'         => $electricienTrade?->id,
            'bio'              => 'Électricien certifié avec 8 ans d\'expérience en bâtiment résidentiel et commercial.',
            'experience_years' => 8,
        ]);

        $plomberieSector = Sector::where('name', 'Plomberie')->first();
        $plombierTrade   = Trade::where('name', 'Plombier sanitaire')->first();

        $artisan2 = User::create([
            'phone'          => '+2250200000002',
            'name'           => 'Issa Traoré',
            'password'       => bcrypt('artisan123'),
            'role'           => 'artisan',
            'kyc_status'     => 'actif',
            'score_nzassa'   => 65,
            'commune_id'     => $plateau?->id,
        ]);
        $artisan2->setPosition(5.3590, -4.0100);

        ArtisanProfile::create([
            'user_id'          => $artisan2->id,
            'sector_id'        => $plomberieSector?->id,
            'trade_id'         => $plombierTrade?->id,
            'bio'              => 'Plombier sanitaire spécialisé dans les installations neuves et la rénovation.',
            'experience_years' => 5,
        ]);

        // ── Fournisseur test ──────────────────────────────────────────────────
        $fournisseur = User::create([
            'phone'      => '+2250300000001',
            'name'       => 'Yao Koffi',
            'password'   => bcrypt('fourn123'),
            'role'       => 'fournisseur',
            'kyc_status' => 'actif',
            'commune_id' => $yopougon?->id,
        ]);

        // position NOT NULL → INSERT direct avec la colonne POINT (MySQL 5.7 compatible)
        // Abidjan Yopougon : lng=-4.0620, lat=5.3300
        DB::statement(
            "INSERT INTO fournisseurs_agrees (user_id, nom_boutique, statut, approuve_at, position, created_at, updated_at)
             VALUES (?, ?, 'agree', ?, POINT(?, ?), NOW(), NOW())",
            [$fournisseur->id, "Quincaillerie de l'Espoir", now(), -4.0620, 5.3300]
        );

        // ── Référent de zone ───────────────────────────────────────────────────
        User::create([
            'phone'      => '+2250400000001',
            'name'       => 'Séraphin Dioulo',
            'password'   => bcrypt('referent123'),
            'role'       => 'referent',
            'kyc_status' => 'actif',
        ]);
    }
}
