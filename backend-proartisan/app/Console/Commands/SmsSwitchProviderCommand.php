<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SmsSwitchProviderCommand extends Command
{
    /**
     * Signature de la commande
     */
    protected $signature = 'sms:switch-provider
                            {provider? : Le fournisseur SMS à activer (smspro, orange, log)}
                            {--status : Afficher le fournisseur actif sans modifier}';

    /**
     * Description de la commande
     */
    protected $description = 'Basculer ou afficher la passerelle d\'envoi de SMS active (SmsPro Africa, Orange SMS, ou Log)';

    /**
     * Fournisseurs supportés
     */
    public const SUPPORTED_PROVIDERS = [
        'smspro' => 'SMS Pro Africa (API standard)',
        'orange' => 'Orange SMS API (Paddock / GSMA OneAPI)',
        'log' => 'Mode Log (Simulation / Développement)',
    ];

    public function handle(SmsService $smsService): int
    {
        $currentProvider = $smsService->getProvider();

        // 1. Si option --status demandée ou aucun argument fourni en mode non-interactif
        if ($this->option('status') || (! $this->argument('provider') && ! $this->input->isInteractive())) {
            $this->displayStatus($currentProvider);

            return self::SUCCESS;
        }

        // 2. Récupération du fournisseur souhaité
        $chosenProvider = $this->argument('provider');

        if (! $chosenProvider) {
            $this->displayStatus($currentProvider);
            $this->newLine();

            $chosenProvider = $this->choice(
                'Sélectionnez la passerelle SMS à utiliser',
                array_keys(self::SUPPORTED_PROVIDERS),
                $currentProvider
            );
        }

        $chosenProvider = strtolower(trim((string) $chosenProvider));

        if (! array_key_exists($chosenProvider, self::SUPPORTED_PROVIDERS)) {
            $this->error("❌ Fournisseur invalide : '{$chosenProvider}'. Choix possibles : ".implode(', ', array_keys(self::SUPPORTED_PROVIDERS)));

            return self::FAILURE;
        }

        // 3. Mise à jour du paramètre en base de données
        Setting::updateOrCreate(
            ['key' => 'sms_provider'],
            [
                'value' => $chosenProvider,
                'type' => 'string',
                'group' => 'communication',
                'label' => 'Passerelle API SMS active',
                'description' => 'Fournisseur d\'envoi SMS actif : "smspro" (SMS Pro Africa), "orange" (Orange SMS API), ou "log" (Simulation en journal).',
            ]
        );

        $this->newLine();
        $this->info("✅ La passerelle d'envoi SMS a été basculée avec succès sur : [{$chosenProvider}] (".self::SUPPORTED_PROVIDERS[$chosenProvider].')');
        $this->newLine();

        // 4. Diagnostic de disponibilité des identifiants
        $this->checkCredentials($chosenProvider);

        // 5. Affichage récapitulatif
        $this->displayStatus($chosenProvider);

        return self::SUCCESS;
    }

    /**
     * Affiche l'état actuel de la configuration SMS
     */
    private function displayStatus(string $activeProvider): void
    {
        $this->info('📱 Configuration SMS ProsArtisan');

        $rows = [
            ['Passerelle active', strtoupper($activeProvider).' ('.(self::SUPPORTED_PROVIDERS[$activeProvider] ?? 'Inconnu').')'],
            ['Source de configuration', Setting::where('key', 'sms_provider')->exists() ? 'Base de données (Table settings)' : 'Fichier .env (services.sms.provider)'],
            ['Expéditeur par défaut', config('services.sms.sender_id', 'ProsArtisan')],
        ];

        if ($activeProvider === 'orange') {
            $hasToken = ! empty(config('services.orange_sms.api_token'));
            $hasOAuth = ! empty(config('services.orange_sms.client_id')) && ! empty(config('services.orange_sms.client_secret'));
            $rows[] = ['Orange Base URL', config('services.orange_sms.base_url', 'https://api.orange.com')];
            $rows[] = ['Orange Sender Address', config('services.orange_sms.sender_address', 'tel:+2250000')];
            $rows[] = ['Orange Auth Mode', $hasToken ? 'Bearer Token direct' : ($hasOAuth ? 'OAuth 2.0 (Client Credentials)' : '❌ Non configuré')];
        } elseif ($activeProvider === 'smspro') {
            $hasToken = ! empty(config('services.sms.api_token'));
            $rows[] = ['SMS Pro Base URL', config('services.sms.base_url', 'https://app.smspro.africa/api/v3')];
            $rows[] = ['SMS Pro Token', $hasToken ? '✅ Configuré' : '❌ Non configuré'];
        } else {
            $rows[] = ['Mode Simulation', '✅ Les SMS sont enregistrés dans storage/logs/laravel.log'];
        }

        $this->table(['Paramètre', 'Valeur'], $rows);
    }

    /**
     * Vérifie si les identifiants requis sont présents
     */
    private function checkCredentials(string $provider): void
    {
        if ($provider === 'orange') {
            $hasToken = ! empty(config('services.orange_sms.api_token'));
            $hasOAuth = ! empty(config('services.orange_sms.client_id')) && ! empty(config('services.orange_sms.client_secret'));

            if (! $hasToken && ! $hasOAuth) {
                $this->warn('⚠️  Attention : Les identifiants Orange SMS ne sont pas encore configurés dans votre fichier .env.');
                $this->line('   Ajoutez :');
                $this->line('   ORANGE_SMS_API_TOKEN=votre_token');
                $this->line('   OU');
                $this->line('   ORANGE_SMS_CLIENT_ID=votre_client_id');
                $this->line('   ORANGE_SMS_CLIENT_SECRET=votre_client_secret');
            } else {
                $this->info('🔑 Identifiants Orange SMS détectés et prêts pour les envois réels.');
            }
        } elseif ($provider === 'smspro') {
            if (empty(config('services.sms.api_token'))) {
                $this->warn('⚠️  Attention : SMS_API_TOKEN n\'est pas renseigné dans votre .env.');
            } else {
                $this->info('🔑 Identifiants SMS Pro Africa détectés et prêts.');
            }
        }
    }
}
