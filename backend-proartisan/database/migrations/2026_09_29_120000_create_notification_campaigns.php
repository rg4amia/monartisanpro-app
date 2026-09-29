<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 14, lot D — campagnes push et SMS rédigées depuis le backoffice.
 *
 * - `notification_campaigns` : un envoi ponctuel vers un public ciblé, avec
 *   son cycle brouillon → programmée → en cours → envoyée / annulée.
 * - `notification_campaign_recipients` : un destinataire déjà servi (clé
 *   unique campagne + utilisateur) n'est jamais relancé.
 * - `notification_preferences` : accord explicite aux messages promotionnels
 *   (désactivé par défaut) ; les préférences par domaine suivront au lot E.
 * - `notification_deliveries.campaign_id` : rattache le journal des envois
 *   à la campagne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_campaigns')) {
            Schema::create('notification_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('nature', 20); // service | promotionnel
                $table->string('push_title', 255)->nullable();
                $table->text('push_body')->nullable();
                $table->text('sms_body')->nullable();
                $table->boolean('channel_in_app')->default(true);
                $table->boolean('channel_push')->default(true);
                $table->boolean('channel_sms')->default(false);
                // {roles: [], commune_ids: [], kyc_statuses: [], user_ids: []}
                $table->json('target_json');
                $table->string('open_screen', 20)->default('notifications'); // notifications | home | communication
                $table->foreignId('communication_id')->nullable()->constrained('communications')->nullOnDelete();
                $table->string('status', 20)->default('brouillon');
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('finished_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('served_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('notification_campaign_recipients')) {
            Schema::create('notification_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
                $table->dateTime('created_at');

                $table->unique(['campaign_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->boolean('promotional_push')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('notification_deliveries') && ! Schema::hasColumn('notification_deliveries', 'campaign_id')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                $table->unsignedBigInteger('campaign_id')->nullable()->after('notification_id');
                $table->index('campaign_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_deliveries') && Schema::hasColumn('notification_deliveries', 'campaign_id')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                $table->dropIndex(['campaign_id']);
                $table->dropColumn('campaign_id');
            });
        }

        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_campaign_recipients');
        Schema::dropIfExists('notification_campaigns');
    }
};
