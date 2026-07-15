<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {phone : Numéro de téléphone destinataire} {--message= : Message personnalisé}';
    protected $description = 'Tester l\'envoi de SMS via SmsPro Africa';

    public function handle(SmsService $smsService): int
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?? 'Test ProsArtisan — SMS envoyé avec succès via SmsPro Africa ! 🎉';

        $this->info("📱 Envoi SMS via SmsPro Africa...");
        $this->table(
            ['Paramètre', 'Valeur'],
            [
                ['Provider', config('services.sms.provider')],
                ['Base URL', config('services.sms.base_url')],
                ['Token', substr(config('services.sms.api_token'), 0, 15) . '***'],
                ['Sender ID', config('services.sms.sender_id', 'ProsArtisan')],
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
