<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des litiges de commande (Chantier 21) : l'ouverture était notée
 * sur la commande elle-même (`dispute_reason`, `dispute_opened_at`) et rien ne
 * conservait l'issue. Chaque litige a désormais sa ligne, close avec sa
 * décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_disputes')) {
            Schema::create('order_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->string('statut', 20)->default('ouvert');
                $table->string('outcome', 30)->nullable();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('opened_at');
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'statut']);
            });
        }

        // Reprise des litiges déjà ouverts : une ligne par commande contestée.
        DB::table('orders')
            ->whereNotNull('dispute_opened_at')
            ->orderBy('id')
            ->select(['id', 'client_id', 'status', 'dispute_reason', 'dispute_opened_at', 'updated_at'])
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    if (DB::table('order_disputes')->where('order_id', $order->id)->exists()) {
                        continue;
                    }

                    $open = $order->status === 'disputed';

                    DB::table('order_disputes')->insert([
                        'order_id' => $order->id,
                        'opened_by' => $order->client_id,
                        'reason' => $order->dispute_reason,
                        // Une commande sortie du litige avant ce chantier : l'issue n'a pas été conservée.
                        'statut' => $open ? 'ouvert' : 'resolu',
                        'outcome' => $open ? null : 'non_conservee',
                        'opened_at' => $order->dispute_opened_at,
                        'resolved_at' => $open ? null : $order->updated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_disputes');
    }
};
