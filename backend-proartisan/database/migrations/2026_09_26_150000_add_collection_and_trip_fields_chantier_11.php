<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 11 — encaissement réel des commandes, relances des courses
 * impayées, course au trajet GPS réel, moyen de remboursement choisi.
 *
 * Chaque colonne est ajoutée dans son propre bloc (Règle d'or 55) : une
 * production partiellement migrée reprend là où elle s'est arrêtée.
 */
return new class extends Migration
{
    /** @var array<string, array<string, callable(Blueprint): void>> */
    private function columns(): array
    {
        return [
            'orders' => [
                // Encaissement réel : échéance de paiement, date d'encaissement,
                // code promo appliqué (restitué si la commande expire).
                'payment_expires_at' => fn (Blueprint $t) => $t->dateTime('payment_expires_at')->nullable(),
                'paid_at' => fn (Blueprint $t) => $t->dateTime('paid_at')->nullable(),
                'promo_code' => fn (Blueprint $t) => $t->string('promo_code', 50)->nullable(),
                // Relances de la course impayée.
                'delivery_fare_reminders_count' => fn (Blueprint $t) => $t->unsignedInteger('delivery_fare_reminders_count')->default(0),
                'delivery_fare_last_reminder_at' => fn (Blueprint $t) => $t->dateTime('delivery_fare_last_reminder_at')->nullable(),
                // Course mesurée sur la trace GPS.
                'actual_distance_km' => fn (Blueprint $t) => $t->decimal('actual_distance_km', 8, 2)->nullable(),
                'actual_duration_min' => fn (Blueprint $t) => $t->decimal('actual_duration_min', 8, 1)->nullable(),
                'fare_source' => fn (Blueprint $t) => $t->string('fare_source', 20)->nullable(),
            ],
            'users' => [
                // Restriction pour course impayée (distincte du blocage de compte).
                'payment_restricted_at' => fn (Blueprint $t) => $t->dateTime('payment_restricted_at')->nullable(),
                'payment_restriction_reason' => fn (Blueprint $t) => $t->text('payment_restriction_reason')->nullable(),
            ],
            'litiges' => [
                // Moyen de remboursement déclaré par le client.
                'refund_provider' => fn (Blueprint $t) => $t->string('refund_provider', 30)->nullable(),
                'refund_phone' => fn (Blueprint $t) => $t->string('refund_phone', 20)->nullable(),
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $definition) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $definition($blueprint));
                }
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status') && Schema::hasColumn('orders', 'payment_expires_at')
            && ! Schema::hasIndex('orders', 'orders_status_payment_expires_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['status', 'payment_expires_at'], 'orders_status_payment_expires_idx');
            });
        }

        $this->seedSettings();
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasIndex('orders', 'orders_status_payment_expires_idx')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropIndex('orders_status_payment_expires_idx'));
        }

        foreach ($this->columns() as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', array_column($this->settings(), 'key'))->delete();
        }
    }

    private function settings(): array
    {
        return [
            [
                'key' => 'delivery_fare_reminder_interval_hours',
                'value' => '24',
                'type' => 'integer',
                'group' => 'paiements',
                'label' => 'Délai entre deux relances de course impayée (heures)',
                'description' => 'Une course livrée non réglée par le client est relancée (notification push, SMS) à cet intervalle.',
            ],
            [
                'key' => 'delivery_fare_reminder_max',
                'value' => '5',
                'type' => 'integer',
                'group' => 'paiements',
                'label' => 'Relances avant restriction du compte client',
                'description' => "Au-delà de ce nombre de relances sans paiement, le client est présumé avoir réglé la course hors plateforme (infraction aux conditions d'utilisation) : il ne peut plus commander ni publier de mission jusqu'au paiement.",
            ],
            [
                'key' => 'order_payment_timeout_minutes',
                'value' => '30',
                'type' => 'integer',
                'group' => 'paiements',
                'label' => "Délai de paiement d'une commande de matériaux (minutes)",
                'description' => 'Une commande non payée dans ce délai est annulée ; son stock et son code promo sont restitués.',
            ],
        ];
    }

    private function seedSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->settings() as $setting) {
            if (! DB::table('settings')->where('key', $setting['key'])->exists()) {
                DB::table('settings')->insert($setting + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
};
