<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('supplier_cashouts')) {
            Schema::table('supplier_cashouts', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_cashouts', 'bank_name')) {
                    $table->string('bank_name', 100)->nullable();
                }
                if (! Schema::hasColumn('supplier_cashouts', 'bank_account_number')) {
                    $table->string('bank_account_number', 50)->nullable();
                }
                if (! Schema::hasColumn('supplier_cashouts', 'batch_reference')) {
                    $table->string('batch_reference', 50)->nullable()->index();
                }
                if (! Schema::hasColumn('supplier_cashouts', 'reconciled_at')) {
                    $table->timestamp('reconciled_at')->nullable()->index();
                }
                if (! Schema::hasColumn('supplier_cashouts', 'reconciled_by')) {
                    $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });

            // En MariaDB / MySQL, mettre à jour l'ENUM pour inclure virement_bancaire
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE supplier_cashouts MODIFY COLUMN mode_retrait ENUM('especes_guichet', 'wave', 'orange_money', 'virement_bancaire') NOT NULL DEFAULT 'especes_guichet'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_cashouts')) {
            Schema::table('supplier_cashouts', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_cashouts', 'reconciled_by')) {
                    $table->dropForeign(['reconciled_by']);
                    $table->dropColumn('reconciled_by');
                }
                if (Schema::hasColumn('supplier_cashouts', 'reconciled_at')) {
                    $table->dropColumn('reconciled_at');
                }
                if (Schema::hasColumn('supplier_cashouts', 'batch_reference')) {
                    $table->dropColumn('batch_reference');
                }
                if (Schema::hasColumn('supplier_cashouts', 'bank_account_number')) {
                    $table->dropColumn('bank_account_number');
                }
                if (Schema::hasColumn('supplier_cashouts', 'bank_name')) {
                    $table->dropColumn('bank_name');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE supplier_cashouts MODIFY COLUMN mode_retrait ENUM('especes_guichet', 'wave', 'orange_money') NOT NULL DEFAULT 'especes_guichet'");
            }
        }
    }
};
