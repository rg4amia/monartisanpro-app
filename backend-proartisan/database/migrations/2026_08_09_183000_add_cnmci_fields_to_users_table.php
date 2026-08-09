<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cnmci_number', 100)->nullable()->after('fcm_token');
            $table->string('cnmci_card_url', 255)->nullable()->after('cnmci_number');
            $table->enum('cnmci_status', ['non_renseigne', 'en_attente', 'valide', 'rejete'])
                ->default('non_renseigne')
                ->after('cnmci_card_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cnmci_number', 'cnmci_card_url', 'cnmci_status']);
        });
    }
};
