<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'sms_provider'],
                [
                    'value' => 'smspro',
                    'type' => 'string',
                    'group' => 'communication',
                    'label' => 'Passerelle API SMS active',
                    'description' => 'Fournisseur d\'envoi SMS actif : "smspro" (SMS Pro Africa), "orange" (Orange SMS API), ou "log" (Simulation en journal).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'sms_provider')->delete();
        }
    }
};
