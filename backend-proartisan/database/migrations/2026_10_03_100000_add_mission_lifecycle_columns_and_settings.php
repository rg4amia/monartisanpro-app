<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 — cycle de vie des missions : validation finale par le client,
 * annulation avec pénalité, délai de réponse de l'artisan.
 *
 * Idempotente (Règle d'or 55) : chaque colonne et chaque réglage est vérifié
 * avant d'être ajouté, sans `after()` sur une colonne supposée présente.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'artisan_assigned_at' => 'dateTime',
        'artisan_reminded_at' => 'dateTime',
        'completion_requested_at' => 'dateTime',
        'cancelled_at' => 'dateTime',
        'cancelled_by' => 'unsignedBigInteger',
        'cancellation_reason' => 'string',
        'cancellation_penalty' => 'unsignedBigInteger',
        'cancellation_refund' => 'unsignedBigInteger',
        'cancellation_penalty_rate' => 'decimal',
    ];

    private const SETTINGS = [
        'mission_cancellation_penalty_rate' => [
            'value' => '7',
            'type' => 'float',
            'label' => "Pénalité d'annulation d'une mission financée (%)",
            'description' => 'Part du séquestre retenue par la plateforme quand le client annule une mission déjà financée, avant le début du chantier. Le reste lui est remboursé. De 0 à 100.',
        ],
        'mission_artisan_response_hours' => [
            'value' => '24',
            'type' => 'integer',
            'label' => "Délai de réponse de l'artisan à une demande de devis (heures)",
            'description' => "Passé ce délai sans réponse, la demande est retirée à l'artisan et le client est invité à en choisir un autre. L'artisan est relancé à mi-délai.",
        ],
        'mission_final_approval_hours' => [
            'value' => '72',
            'type' => 'integer',
            'label' => 'Délai de validation finale du chantier par le client (heures)',
            'description' => 'Une fois toutes les étapes payées, le client valide la fin du chantier. Sans réponse passé ce délai, la mission est clôturée automatiquement.',
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column => $type) {
            if (Schema::hasColumn('missions', $column)) {
                continue;
            }

            Schema::table('missions', function (Blueprint $table) use ($column, $type): void {
                match ($type) {
                    'dateTime' => $table->dateTime($column)->nullable(),
                    'unsignedBigInteger' => $table->unsignedBigInteger($column)->nullable(),
                    'decimal' => $table->decimal($column, 5, 2)->nullable(),
                    default => $table->string($column, 255)->nullable(),
                };
            });
        }

        // Les demandes déjà en attente d'un artisan reçoivent le délai complet
        // à partir de maintenant : leur date d'envoi réelle n'a pas été conservée.
        DB::table('missions')
            ->where('status', 'pending_artisan_acceptance')
            ->whereNull('artisan_assigned_at')
            ->update(['artisan_assigned_at' => now()]);

        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::SETTINGS as $key => $setting) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'group' => 'missions',
                'created_at' => now(),
                'updated_at' => now(),
                ...$setting,
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::COLUMNS) as $column) {
            if (Schema::hasColumn('missions', $column)) {
                Schema::table('missions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
        }
    }
};
