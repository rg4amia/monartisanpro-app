<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 14, lot E — préférences de notification par rubrique.
 *
 * - `muted_push_domains` / `muted_sms_domains` : rubriques du catalogue dont
 *   l'utilisateur a coupé le push ou le SMS (null = rien de coupé) ;
 * - `promotional_push_at` : date de l'accord aux offres et nouveautés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        if (! Schema::hasColumn('notification_preferences', 'promotional_push_at')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->dateTime('promotional_push_at')->nullable();
            });
        }

        if (! Schema::hasColumn('notification_preferences', 'muted_push_domains')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->json('muted_push_domains')->nullable();
            });
        }

        if (! Schema::hasColumn('notification_preferences', 'muted_sms_domains')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->json('muted_sms_domains')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        foreach (['muted_sms_domains', 'muted_push_domains', 'promotional_push_at'] as $column) {
            if (Schema::hasColumn('notification_preferences', $column)) {
                Schema::table('notification_preferences', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
