<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 60); // collusion_artisan_client, collusion_artisan_fournisseur, fast_otp_validation, gps_anomaly_site, gps_anomaly_jcode, duplicate_proof
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->unsignedSmallInteger('risk_score')->default(50); // 0-100
            $table->json('reasons_json');
            $table->json('metadata_json')->nullable();
            $table->string('statut', 30)->default('ouverte'); // ouverte, en_analyse, confirmee, rejetee, classee
            $table->string('action_taken', 30)->default('none'); // none, payment_hold, flagged_account, blocked
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['statut', 'severity']);
            $table->index('type');
            $table->index('risk_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
    }
};
