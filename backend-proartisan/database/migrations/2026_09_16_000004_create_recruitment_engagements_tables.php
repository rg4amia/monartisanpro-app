<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement de recrutement : une candidature confirmée (« Retenu ») donne
 * lieu à un contrat journalier séquestré — l'artisan doit l'accepter, le
 * recruteur paie l'intégralité sous séquestre, et chaque jour travaillé est
 * libéré individuellement après validation du client (`recruitment_workdays`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('recruitment_offers');
            $table->foreignId('application_id')->unique()->constrained('recruitment_applications');
            $table->foreignId('artisan_id')->constrained('users');
            $table->foreignId('recruiter_id')->constrained('users');
            $table->bigInteger('daily_rate');
            $table->smallInteger('total_days')->unsigned();
            $table->bigInteger('montant_total');
            $table->decimal('commission_rate', 5, 4);
            $table->enum('status', [
                'pending_artisan_acceptance',
                'pending_payment',
                'active',
                'completed',
                'cancelled',
            ])->default('pending_artisan_acceptance');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_workdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->constrained('recruitment_engagements')->cascadeOnDelete();
            $table->smallInteger('day_number')->unsigned();
            $table->bigInteger('montant');
            $table->enum('status', ['awaiting_payment', 'pending', 'validated'])->default('awaiting_payment');
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['engagement_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_workdays');
        Schema::dropIfExists('recruitment_engagements');
    }
};
