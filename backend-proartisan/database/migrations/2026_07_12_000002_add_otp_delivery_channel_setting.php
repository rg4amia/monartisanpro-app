<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert the new setting for OTP channel selection if it does not exist
        DB::table('settings')->updateOrInsert(
            ['key' => 'otp_delivery_channel'],
            [
                'value' => 'sms',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Canal d\'envoi des codes OTP',
                'description' => 'Sélectionnez le canal d\'envoi par défaut pour les codes OTP : "sms" (SMS uniquement), "whatsapp" (WhatsApp uniquement), ou "both" (SMS et WhatsApp).',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'otp_delivery_channel')->delete();
    }
};
