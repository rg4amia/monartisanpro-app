<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 14, lot B — messages de notification pilotables et journal des envois.
 *
 * - `notification_templates` : surcharges des textes et canaux d'un événement du
 *   catalogue (`App\Services\Notifications\NotificationCatalog`). Une ligne
 *   n'existe que si un administrateur a modifié l'événement ; sans ligne, le
 *   texte d'origine du code s'applique.
 * - `notification_deliveries` : une ligne par tentative d'envoi push ou SMS
 *   (jamais modifiée), pour l'observabilité et le futur journal du backoffice.
 * - `notifications.event_key` : événement du catalogue à l'origine de la
 *   notification in-app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 100)->unique();
                $table->string('push_title', 255)->nullable();
                $table->text('push_body')->nullable();
                $table->text('sms_body')->nullable();
                // NULL = valeur par défaut du catalogue.
                $table->boolean('channel_in_app')->nullable();
                $table->boolean('channel_push')->nullable();
                $table->boolean('channel_sms')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_key', 100)->nullable();
                $table->string('channel', 10); // push | sms
                $table->string('provider', 20)->nullable(); // onesignal | smspro | orange | log
                $table->string('status', 10); // envoye | echoue | ignore
                $table->string('reason', 500)->nullable();
                $table->dateTime('created_at');

                $table->index(['status', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'event_key')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('event_key', 100)->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');

        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'event_key')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('event_key');
            });
        }
    }
};
