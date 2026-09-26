<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retraits des gains livreur, sur le modèle du cash-out quincaillerie.
 * Frais réglables (`commission_cashout_livreur`), 0 % par défaut : le
 * livreur a déjà acquitté la commission plateforme sur chaque course.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_cashouts')) {
            Schema::create('driver_cashouts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 50)->unique();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->string('beneficiary_name', 150);
                $table->string('beneficiary_phone', 20);
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account_number', 50)->nullable();
                $table->unsignedBigInteger('montant_brut');
                $table->decimal('commission_rate', 5, 4)->default(0);
                $table->unsignedBigInteger('montant_commission')->default(0);
                $table->unsignedBigInteger('montant_net');
                $table->string('statut', 20)->default('en_attente');
                $table->string('mode_retrait', 30)->default('wave');
                $table->text('notes')->nullable();
                $table->foreignId('payout_id')->nullable()->constrained('mobile_money_payouts')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->index(['driver_id', 'statut']);
                $table->index('statut');
            });
        }

        if (Schema::hasTable('settings') && ! DB::table('settings')->where('key', 'commission_cashout_livreur')->exists()) {
            DB::table('settings')->insert([
                'key' => 'commission_cashout_livreur',
                'value' => '0',
                'type' => 'float',
                'group' => 'commissions',
                'label' => 'Frais de retrait des gains livreur',
                'description' => "Frais prélevés sur chaque retrait des gains d'un livreur (en ratio, ex : 0.01 pour 1 %). 0 par défaut : la commission plateforme est déjà prélevée sur chaque course.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_cashouts');

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'commission_cashout_livreur')->delete();
        }
    }
};
