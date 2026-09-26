<?php

namespace App\Console\Commands;

use App\Services\EvidenceVaultService;
use Illuminate\Console\Command;

class VerifyEvidenceVaultIntegrityCommand extends Command
{
    protected $signature = 'vault:verify-integrity';

    protected $description = 'Vérifie l\'intégrité cryptographique SHA-256 de toutes les preuves du coffre-fort (Evidence Vault)';

    public function handle(EvidenceVaultService $vaultService): int
    {
        $this->info('🔐 Démarrage de l\'audit d\'intégrité du coffre-fort des preuves...');

        $result = $vaultService->verifyAll();

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total des preuves archivées', $result['total']],
                ['Preuves intactes certifiées', $result['intact']],
                ['Altérations / fichiers introuvables', $result['tampered']],
            ]
        );

        if ($result['tampered'] > 0) {
            $this->error('⚠️ Alerte : des altérations de preuves ont été détectées sur les ID : '.implode(', ', $result['tampered_ids']));
            return self::FAILURE;
        }

        $this->info('✅ Audit terminé : 100% des preuves du coffre-fort sont intègres et conformes.');
        return self::SUCCESS;
    }
}
