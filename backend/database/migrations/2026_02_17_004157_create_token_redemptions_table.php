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
        Schema::create('token_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('material_tokens')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('artisan_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->geometry('vendor_location', 'point')->nullable();
            $table->geometry('artisan_location', 'point')->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->enum('validation_method', ['gps', 'otp_sms', 'admin_override']);
            $table->string('receipt_photo')->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index('token_id');
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_redemptions');
    }
};
