<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: This migration is no longer needed for MySQL.
     * MySQL has built-in spatial support without requiring extensions.
     */
    public function up(): void
    {
        // No action needed for MySQL - spatial support is built-in
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed
    }
};
