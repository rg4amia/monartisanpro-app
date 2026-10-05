<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte des codes de retrait et de réception faux, et suspension de la
 * validation après trop d'essais (Chantier 32) : un code de 4 chiffres se
 * trouvait par essais successifs.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'pickup_code_attempts' => 'counter',
        'pickup_code_locked_until' => 'date',
        'reception_code_attempts' => 'counter',
        'reception_code_locked_until' => 'date',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        foreach (self::COLUMNS as $column => $kind) {
            if (Schema::hasColumn('orders', $column)) {
                continue;
            }

            Schema::table('orders', function (Blueprint $table) use ($column, $kind) {
                $kind === 'counter'
                    ? $table->unsignedSmallInteger($column)->default(0)
                    : $table->dateTime($column)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::COLUMNS) as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
