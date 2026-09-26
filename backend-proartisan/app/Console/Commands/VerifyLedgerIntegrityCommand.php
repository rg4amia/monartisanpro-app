<?php

namespace App\Console\Commands;

use App\Services\DoubleEntryLedgerService;
use Illuminate\Console\Command;

class VerifyLedgerIntegrityCommand extends Command
{
    protected $signature = 'ledger:verify-integrity {--mission= : ID spécifique de mission à vérifier}';

    protected $description = 'Vérifie l\'intégrité mathématique en partie double du grand livre financier (BCEAO)';

    public function handle(DoubleEntryLedgerService $ledgerService): int
    {
        $missionId = $this->option('mission') ? (int) $this->option('mission') : null;

        $this->info('─────────────────────────────────────────────────────────────────');
        $this->info(' 🏛️  PROSARTISAN — AUDIT DU GRAND LIVRE EN PARTIE DOUBLE (LOT 9A)');
        $this->info('─────────────────────────────────────────────────────────────────');

        $result = $ledgerService->verifyIntegrity($missionId);

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Écritures au grand livre', number_format($result['total_entries_count'], 0, ',', ' ')],
                ['Volume total audité', number_format($result['total_volume_xof'], 0, ',', ' ').' FCFA'],
                ['Équilibre mathématique', $result['balanced'] ? '✅ PARFAIT (0 écart)' : '❌ ANOMALIE DÉTECTÉE'],
                ['Anomalies comptables', (string) $result['anomalies_count']],
                ['Horodatage de certification', $result['timestamp']],
            ]
        );

        if (! $result['balanced'] || $result['anomalies_count'] > 0) {
            $this->error('Échec de conformité comptable : des écarts ont été constatés.');
            return self::FAILURE;
        }

        $this->info('✅ Audit réussi : le grand livre en partie double est 100% conforme et équilibré.');
        return self::SUCCESS;
    }
}
