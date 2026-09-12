<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Affiche les URL de rappel à déclarer chez l'opérateur (SMSpro, passerelle
 * USSD), avec la façon d'y porter le secret d'authentification.
 *
 * Les deux endpoints valident des retraits et des livraisons : ils libèrent
 * des fonds. Une URL mal recopiée se traduit soit par une panne silencieuse
 * (rappels refusés en 401), soit par une tentation de désactiver le contrôle.
 */
class GatewayCallbackUrlCommand extends Command
{
    protected $signature = 'gateway:callback-url
                            {--reveal : Affiche le secret en clair et les URL prêtes à coller}';

    protected $description = "Affiche les URL de rappel USSD/SMS à configurer chez l'opérateur";

    public function handle(): int
    {
        $secret = (string) config('services.gateway.secret', '');
        $signingSecret = (string) config('services.gateway.signing_secret', '');
        $base = rtrim((string) config('app.url'), '/');

        if ($secret === '' && $signingSecret === '') {
            $this->error('USSD_GATEWAY_SECRET n\'est pas défini : les endpoints de validation hors-ligne répondent 503.');
            $this->line('Générez-en un puis placez-le dans le .env :');
            $this->newLine();
            $this->line('  USSD_GATEWAY_SECRET='.bin2hex(random_bytes(24)));
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('Endpoints de validation hors-ligne');
        $this->newLine();

        $this->line('  SMS entrant : '.$base.'/api/v1/sms/incoming');
        $this->line('  USSD        : '.$base.'/api/v1/ussd');
        $this->newLine();

        $this->comment('Méthode 0 — signature HMAC SMSpro (la plus sûre)');

        if ($signingSecret === '') {
            $this->line('  SMSPRO_WEBHOOK_SECRET non défini.');
            $this->line('  Récupérer le secret sur la page « Developer settings » de SMSpro,');
            $this->line('  puis le placer dans le .env. SMSpro signera alors chaque appel');
            $this->line('  (X-Webhook-Signature: sha256=…) et la signature couvrira le contenu.');
        } else {
            $this->line('  Actif : les appels signés par SMSpro sont vérifiés en HMAC-SHA256');
            $this->line('  sur le corps brut. Aucun secret à placer dans l\'URL dans ce cas.');
        }

        $this->newLine();
        $this->comment('Méthode 1 — en-tête HTTP (secret partagé)');

        if ($secret === '') {
            $this->line('  USSD_GATEWAY_SECRET non défini : seuls les appels signés seront acceptés.');
            $this->line('  À renseigner pour la passerelle USSD, qui ne signe pas ses appels.');
        } else {
            $this->line('  Ajouter cet en-tête dans la configuration du rappel :');
            $this->line('    X-Gateway-Secret: '.($this->option('reveal') ? $secret : str_repeat('•', 12).' (--reveal pour l\'afficher)'));
        }

        $this->newLine();
        $this->comment('Méthode 2 — paramètre d\'URL');

        if ($secret === '') {
            $this->line('  Indisponible tant que USSD_GATEWAY_SECRET est vide.');
        } else {
            $this->line("  Si l'opérateur ne permet ni signature ni en-tête personnalisé :");

            if ($this->option('reveal')) {
                $this->line('    '.$base.'/api/v1/sms/incoming?gateway_secret='.$secret);
                $this->line('    '.$base.'/api/v1/ussd?gateway_secret='.$secret);
            } else {
                $this->line('    '.$base.'/api/v1/sms/incoming?gateway_secret=…  (--reveal pour l\'afficher)');
            }

            $this->newLine();
            $this->warn('Le paramètre d\'URL apparaît dans les journaux d\'accès du serveur : préférez la signature ou l\'en-tête.');
        }

        $ips = trim((string) config('services.gateway.ips', ''));
        $this->newLine();
        $this->line($ips === ''
            ? 'Liste blanche d\'IP : aucune (USSD_GATEWAY_IPS vide). La renseigner si SMSpro publie des IP sortantes fixes.'
            : 'Liste blanche d\'IP active : '.$ips);

        return self::SUCCESS;
    }
}
