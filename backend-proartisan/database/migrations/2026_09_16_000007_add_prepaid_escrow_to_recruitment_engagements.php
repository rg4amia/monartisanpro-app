<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report du séquestre d'accès aux candidatures (payé au niveau de l'offre)
 * sur l'engagement effectivement créé avec le candidat retenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_engagements', function (Blueprint $table) {
            $table->foreignId('prepaid_transaction_id')->nullable()->after('commission_rate')
                ->constrained('transactions')->nullOnDelete();
            $table->bigInteger('prepaid_amount')->default(0)->after('prepaid_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_engagements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepaid_transaction_id');
            $table->dropColumn('prepaid_amount');
        });
    }
};
