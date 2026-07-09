<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // 'string', 'integer', 'float', 'json', 'boolean'
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default values
        DB::table('settings')->insert([
            [
                'key' => 'commission_service',
                'value' => '0.00',
                'type' => 'float',
                'group' => 'commissions',
                'label' => 'Commission sur Main d\'Œuvre (Artisans)',
                'description' => 'Frais de plateforme prélevés sur les jalons de main d\'œuvre payés aux artisans (en ratio, ex: 0.10 pour 10%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'commission_fournisseur',
                'value' => '0.05',
                'type' => 'float',
                'group' => 'commissions',
                'label' => 'Commission Fournisseur / Matériaux',
                'description' => 'Commission prélevée sur la vente des matériaux par les quincailleries agréées (en ratio, ex: 0.05 pour 5%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'platform_fee_ratio',
                'value' => '0.03',
                'type' => 'float',
                'group' => 'commissions',
                'label' => 'Frais de Service Plateforme',
                'description' => 'Frais de service plateforme facturés au client final sur le montant des matériaux achetés (en ratio, ex: 0.03 pour 3%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'commission_livreur',
                'value' => '0.10',
                'type' => 'float',
                'group' => 'commissions',
                'label' => 'Commission sur Course Livreur',
                'description' => 'Commission prélevée sur les frais de course payés aux livreurs (en ratio, ex: 0.10 pour 10%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'commission_categories',
                'value' => json_encode([
                    'macon' => 0.05,
                    'plombier' => 0.05,
                    'electricien' => 0.07,
                    'peintre' => 0.04,
                ]),
                'type' => 'json',
                'group' => 'commissions',
                'label' => 'Commission par Catégorie d\'Artisan',
                'description' => 'Ajustement de commission par métier pour moduler les frais (en JSON).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
