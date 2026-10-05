<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\Llm\KnowledgeBaseService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Rédaction par Gemini des fiches d'un document importé, exécutée juste après
 * la réponse HTTP (dispatchAfterResponse) : l'analyse d'un PDF peut durer plus
 * d'une minute et aucun worker de file d'attente n'est requis (Chantier 23).
 */
class GenerateKnowledgeSheetsJob
{
    use Dispatchable;

    public function __construct(public string $importId, public int $adminId) {}

    public function handle(KnowledgeBaseService $knowledge): void
    {
        // Le délai ne vaut que pour cette génération : laissé en place, il
        // s'appliquait à tout le processus (la suite de tests, exécutée dans
        // le même processus, était coupée 180 s plus tard).
        $previousLimit = (int) ini_get('max_execution_time');
        @set_time_limit(180);

        try {
            $knowledge->generateSheets($this->importId, $this->adminId);
        } catch (\Throwable $e) {
            // Sans cela, l'import resterait indéfiniment « en cours ».
            Log::error('Génération des fiches : exception', ['import' => $this->importId, 'message' => $e->getMessage()]);
            ImportHistory::whereKey($this->importId)->update([
                'status' => ImportHistory::STATUS_FAILED,
                'error_message' => 'La génération des fiches a échoué. Réessayez plus tard.',
            ]);
        } finally {
            @set_time_limit($previousLimit);
        }
    }
}
