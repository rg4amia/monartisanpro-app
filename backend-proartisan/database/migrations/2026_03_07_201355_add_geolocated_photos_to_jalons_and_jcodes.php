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
        // Jalons : photos preuves géolocalisées pour validation client
        Schema::table('jalons', function (Blueprint $table) {
            if (!Schema::hasColumn('jalons', 'photos_json')) {
                $table->json('photos_json')->nullable()->after('description')
                      ->comment('Photos géolocalisées de preuve de travail');
            }
        });

        // JCodes : photo géolocalisée des matériaux reçus sur chantier
        Schema::table('jcodes', function (Blueprint $table) {
            if (!Schema::hasColumn('jcodes', 'photo_materiaux_url')) {
                $table->string('photo_materiaux_url', 500)->nullable()->after('ussd_code')
                      ->comment('URL photo matériaux livrés sur chantier');
                $table->decimal('photo_latitude', 10, 8)->nullable()->after('photo_materiaux_url')
                      ->comment('Latitude GPS de la photo matériaux');
                $table->decimal('photo_longitude', 11, 8)->nullable()->after('photo_latitude')
                      ->comment('Longitude GPS de la photo matériaux');
                $table->timestamp('photo_taken_at')->nullable()->after('photo_longitude')
                      ->comment('Date/heure de prise de la photo matériaux');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jalons', function (Blueprint $table) {
            if (Schema::hasColumn('jalons', 'photos_json')) {
                $table->dropColumn('photos_json');
            }
        });

        Schema::table('jcodes', function (Blueprint $table) {
            if (Schema::hasColumn('jcodes', 'photo_materiaux_url')) {
                $table->dropColumn([
                    'photo_materiaux_url',
                    'photo_latitude',
                    'photo_longitude',
                    'photo_taken_at',
                ]);
            }
        });
    }
};
