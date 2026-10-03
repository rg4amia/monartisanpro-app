<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Une personne n'est évaluée qu'une fois par un même client pour une même
 * mission ou une même commande. Le contrôle ne reposait que sur une lecture
 * préalable : deux envois simultanés créaient deux évaluations.
 */
return new class extends Migration
{
    private const INDEXES = [
        'evaluations_mission_unique' => ['mission_id', 'evaluateur_id', 'evalue_id'],
        'evaluations_order_unique' => ['order_id', 'evaluateur_id', 'evalue_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('evaluations')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasColumn('evaluations', $columns[0]) || Schema::hasIndex('evaluations', $name)) {
                continue;
            }

            // Des doublons déjà présents feraient échouer la création de l'index :
            // ils sont signalés, jamais supprimés d'office (ce sont des notes réelles).
            $duplicates = DB::table('evaluations')
                ->whereNotNull($columns[0])
                ->select($columns)
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($duplicates->isNotEmpty()) {
                Log::warning("Index {$name} non créé : évaluations en double à traiter", ['doublons' => $duplicates->count()]);

                continue;
            }

            Schema::table('evaluations', fn (Blueprint $table) => $table->unique($columns, $name));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluations')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('evaluations', $name)) {
                Schema::table('evaluations', fn (Blueprint $table) => $table->dropUnique($name));
            }
        }
    }
};
