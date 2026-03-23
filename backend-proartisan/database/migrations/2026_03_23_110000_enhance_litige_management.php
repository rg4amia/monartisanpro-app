<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('actif')->after('kyc_status');
            $table->text('account_status_reason')->nullable()->after('account_status');
            $table->timestamp('blocked_at')->nullable()->after('account_status_reason');
        });

        Schema::table('litiges', function (Blueprint $table) {
            $table->string('motif', 120)->nullable()->after('type');
            $table->string('workflow_step', 40)->default('preuves')->after('statut');
            $table->timestamp('funds_locked_at')->nullable()->after('workflow_step');
            $table->timestamp('evidence_deadline_at')->nullable()->after('funds_locked_at');
            $table->timestamp('arbitration_started_at')->nullable()->after('evidence_deadline_at');
            $table->timestamp('arbitration_deadline_at')->nullable()->after('arbitration_started_at');
            $table->timestamp('client_evidence_submitted_at')->nullable()->after('arbitration_deadline_at');
            $table->timestamp('artisan_evidence_submitted_at')->nullable()->after('client_evidence_submitted_at');
            $table->foreignId('resolved_by')->nullable()->after('decision')->constrained('users');
            $table->string('resolution_reason', 120)->nullable()->after('admin_notes');
            $table->json('resolution_payload')->nullable()->after('resolution_reason');
            $table->json('sanctions_json')->nullable()->after('resolution_payload');
        });

        Schema::create('litige_preuves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litige_id')->constrained('litiges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('partie', 20);
            $table->text('description')->nullable();
            $table->text('media_url');
            $table->string('media_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['litige_id', 'partie']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE missions MODIFY status VARCHAR(32) NOT NULL DEFAULT 'en_attente'");
            DB::statement("ALTER TABLE litiges MODIFY statut VARCHAR(32) NOT NULL DEFAULT 'ouvert'");
            DB::statement('ALTER TABLE litiges MODIFY decision VARCHAR(32) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE litiges MODIFY decision ENUM('client','artisan','gel') NULL");
            DB::statement("ALTER TABLE litiges MODIFY statut ENUM('ouvert','en_cours','resolu') NOT NULL DEFAULT 'ouvert'");
            DB::statement("ALTER TABLE missions MODIFY status ENUM('en_attente','financee','en_cours','terminee','litige') NOT NULL DEFAULT 'en_attente'");
        }

        Schema::dropIfExists('litige_preuves');

        Schema::table('litiges', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn([
                'motif',
                'workflow_step',
                'funds_locked_at',
                'evidence_deadline_at',
                'arbitration_started_at',
                'arbitration_deadline_at',
                'client_evidence_submitted_at',
                'artisan_evidence_submitted_at',
                'resolved_by',
                'resolution_reason',
                'resolution_payload',
                'sanctions_json',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_status',
                'account_status_reason',
                'blocked_at',
            ]);
        });
    }
};
