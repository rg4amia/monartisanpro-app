<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Base de connaissances de l'Assistant IA (Chantier 23) : documents sur le
 * disque privé, fiches rattachées à leur document, origine et auteurs tracés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attachments')) {
            if (! Schema::hasColumn('attachments', 'disk')) {
                Schema::table('attachments', fn (Blueprint $table) => $table->string('disk', 20)->default('public'));
            }
            if (! Schema::hasColumn('attachments', 'path')) {
                Schema::table('attachments', fn (Blueprint $table) => $table->string('path', 500)->nullable());
            }
            if (! Schema::hasColumn('attachments', 'mime_type')) {
                Schema::table('attachments', fn (Blueprint $table) => $table->string('mime_type', 100)->nullable());
            }
            if (! Schema::hasColumn('attachments', 'size')) {
                Schema::table('attachments', fn (Blueprint $table) => $table->unsignedBigInteger('size')->nullable());
            }
        }

        if (Schema::hasTable('import_history')) {
            if (! Schema::hasColumn('import_history', 'attachment_id')) {
                Schema::table('import_history', fn (Blueprint $table) => $table->string('attachment_id', 100)->nullable());
            }
            if (! Schema::hasColumn('import_history', 'uploaded_by_id')) {
                Schema::table('import_history', fn (Blueprint $table) => $table->unsignedBigInteger('uploaded_by_id')->nullable());
            }
            if (! Schema::hasColumn('import_history', 'error_message')) {
                Schema::table('import_history', fn (Blueprint $table) => $table->text('error_message')->nullable());
            }
            if (! Schema::hasColumn('import_history', 'generation_started_at')) {
                Schema::table('import_history', fn (Blueprint $table) => $table->dateTime('generation_started_at')->nullable());
            }
            if (! Schema::hasColumn('import_history', 'sheets_count')) {
                Schema::table('import_history', fn (Blueprint $table) => $table->unsignedInteger('sheets_count')->default(0));
            }
        }

        if (Schema::hasTable('staging_items')) {
            if (! Schema::hasColumn('staging_items', 'import_id')) {
                Schema::table('staging_items', fn (Blueprint $table) => $table->string('import_id', 100)->nullable());
            }
            if (! Schema::hasColumn('staging_items', 'origin')) {
                Schema::table('staging_items', fn (Blueprint $table) => $table->string('origin', 20)->default('manuelle'));
            }
            if (! Schema::hasColumn('staging_items', 'model_name')) {
                Schema::table('staging_items', fn (Blueprint $table) => $table->string('model_name', 100)->nullable());
            }
            if (! Schema::hasColumn('staging_items', 'created_by_id')) {
                Schema::table('staging_items', fn (Blueprint $table) => $table->unsignedBigInteger('created_by_id')->nullable());
            }
            if (! Schema::hasColumn('staging_items', 'validated_by_id')) {
                Schema::table('staging_items', fn (Blueprint $table) => $table->unsignedBigInteger('validated_by_id')->nullable());
            }
        }

        $this->moveLegacyDocumentsToPrivateDisk();
    }

    /**
     * Les documents importés avant ce chantier étaient servis sans
     * authentification depuis `/storage/fileshare` : ils rejoignent le disque privé.
     */
    private function moveLegacyDocumentsToPrivateDisk(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasTable('attachments')) {
            return;
        }

        try {
            $public = Storage::disk('public');

            foreach (DB::table('attachments')->where('disk', 'public')->get() as $row) {
                $name = basename((string) $row->file_link);
                $source = "fileshare/{$name}";
                $target = "llm-documents/{$name}";

                if ($name !== '' && $public->exists($source)) {
                    Storage::disk('local')->put($target, $public->get($source));
                    $public->delete($source);
                }

                DB::table('attachments')->where('id', $row->id)->update(['disk' => 'local', 'path' => $target]);
            }

            // Tout fichier restant dans ce dossier public n'est rattaché à rien.
            $public->deleteDirectory('fileshare');
        } catch (Throwable $e) {
            Log::warning('Documents de la base de connaissances : déplacement vers le disque privé incomplet', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Colonnes conservées : les retirer ferait perdre le rattachement des fiches.
    }
};
