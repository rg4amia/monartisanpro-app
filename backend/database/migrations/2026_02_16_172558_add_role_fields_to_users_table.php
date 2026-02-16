<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['client', 'artisan', 'fournisseur', 'referent', 'admin'])->after('email');
            $table->string('phone', 20)->nullable()->after('role');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending')->after('avatar');
            $table->timestamp('phone_verified_at')->nullable()->after('kyc_status');
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active')->after('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'avatar', 'kyc_status', 'phone_verified_at', 'status']);
        });
    }
};
