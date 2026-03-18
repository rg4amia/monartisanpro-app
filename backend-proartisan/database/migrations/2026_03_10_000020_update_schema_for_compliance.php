<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jcodes', function (Blueprint $table) {
            $table->enum('paiement_status', ['en_attente', 'programme', 'paye'])->default('en_attente')->after('statut');
            $table->timestamp('paye_at')->nullable()->after('scanned_at');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->boolean('funds_frozen')->default(false)->after('referent_required');
            $table->timestamp('referent_validated_at')->nullable()->after('funds_frozen');
            $table->foreignId('referent_validated_by')->nullable()->after('referent_validated_at')->constrained('users');
        });

        Schema::table('litiges', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('decision');
        });

        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->bigInteger('amount');
            $table->tinyInteger('score_nzassa_at_application');
            $table->enum('status', ['en_attente', 'approuve', 'rejete', 'debourse', 'rembourse'])->default('en_attente');
            $table->string('external_reference')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');

        Schema::table('litiges', function (Blueprint $table) {
            $table->dropColumn('admin_notes');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign(['referent_validated_by']);
            $table->dropColumn(['funds_frozen', 'referent_validated_at', 'referent_validated_by']);
        });

        Schema::table('jcodes', function (Blueprint $table) {
            $table->dropColumn(['paiement_status', 'paye_at']);
        });
    }
};
