<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {phone : Numéro de téléphone destinataire} {--provider= : Forcer un fournisseur (smspro, orange, log)} {--message= : Message personnalisé}';

    protected $description = 'Tester l\'envoi de SMS via la passerelle active (SmsPro Africa ou Orange SMS)';

    public function handle(SmsService $smsService): int
    {
        $phone = $this->argument('phone');
        $forcedProvider = $this->option('provider');
        if ($forcedProvider) {
            $smsService->setProvider(strtolower(trim($forcedProvider)));
        }

        $activeProvider = $smsService->getProvider();
        $message = $this->option('message') ?? "Test ProsArtisan — SMS envoyé avec succès via [{$activeProvider}] ! 🎉";

        $this->info("📱 Envoi SMS de test via [{$activeProvider}]...");
        $this->table(
            ['Paramètre', 'Valeur'],
            [
                ['Passerelle', strtoupper($activeProvider)],
                ['Expéditeur', config('services.sms.sender_id', 'ProsArtisan')],
                ['Destinataire', $phone],
                ['Message', $message],
            ]
        );

        $this->newLine();
        $this->info('⏳ Envoi en cours...');

        $result = $smsService->send($phone, $message);

        $this->newLine();

        if (($result['status'] ?? '') === 'success' || isset($result['data'])) {
            $this->info('✅ SMS envoyé avec succès !');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->error('❌ Échec de l\'envoi SMS');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::FAILURE;
    }
}
