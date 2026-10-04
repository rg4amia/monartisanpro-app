<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 28 — le rôle `driver` n'existe pas : le rôle des livreurs est
 * `livreur` (Règle d'or 63). Ses lignes de droits, inscrites par l'ancien
 * seeder, n'avaient aucun effet et n'apparaissaient plus à l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        DB::table('permission_role')->where('role', 'driver')->delete();
        Cache::forget('role_permissions_driver');
    }

    public function down(): void
    {
        // Rien à rétablir : ces lignes n'avaient aucun effet.
    }
};
