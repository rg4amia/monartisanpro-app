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
        if (! Schema::hasTable('device_fingerprints')) {
            Schema::create('device_fingerprints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_fingerprint', 128)->index();
                $table->string('device_model', 100)->nullable();
                $table->string('app_installation_id', 128)->nullable()->index();
                $table->string('ip_address', 45)->nullable()->index();
                $table->string('ip_subnet', 30)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'device_fingerprint']);
            });
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'client_device_fingerprint')) {
                $table->string('client_device_fingerprint', 128)->nullable()->after('client_id');
            }
            if (! Schema::hasColumn('missions', 'artisan_device_fingerprint')) {
                $table->string('artisan_device_fingerprint', 128)->nullable()->after('artisan_id');
            }
            if (! Schema::hasColumn('missions', 'client_ip')) {
                $table->string('client_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('missions', 'artisan_ip')) {
                $table->string('artisan_ip', 45)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn([
                'client_device_fingerprint',
                'artisan_device_fingerprint',
                'client_ip',
                'artisan_ip',
            ]);
        });

        Schema::dropIfExists('device_fingerprints');
    }
};
