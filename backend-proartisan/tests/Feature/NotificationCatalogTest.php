<?php

namespace Tests\Feature;

use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\NotificationService;
use Tests\TestCase;

/**
 * Chantier 14, lot B — cohérence du catalogue des événements de notification
 * et de ses points d'émission dans le code.
 */
class NotificationCatalogTest extends TestCase
{
    /**
     * SMS par défaut, décision métier du 29/09/2026 : OTP, paiements reçus,
     * fraude visant l'utilisateur, ouverture d'un litige ; côté
     * administrateurs, fraude et courses bloquées. Modifier cette liste est
     * un choix métier, pas un détail technique.
     */
    private const EXPECTED_SMS_EVENTS = [
        // Code à usage unique (Règle d'or 39)
        'auth.otp',
        // Paiements reçus
        'jalon.paye.artisan',
        'jalon.libere_auto.artisan',
        'jalon.preuves_acceptees.artisan',
        'jcode.paiement_fournisseur',
        'commande.retrait_valide.fournisseur',
        'course.terminee_creditee.livreur',
        'course.reglee.livreur',
        'retrait_livreur.verse',
        'credit.accorde.artisan',
        'recrutement.trop_percu.recruteur',
        'recrutement.journee_payee.artisan',
        'recrutement.mission_terminee.artisan',
        // Fraude et sécurité visant l'utilisateur
        'jalon.controle_securite.artisan',
        'securite.changement_appareil.artisan',
        // Ouverture d'un litige
        'litige.ouvert.partie',
        'commande.litige.fournisseur',
        'commande.litige.livreur',
        // Course bloquée : relance du livreur (Règle d'or 53)
        'livraison.confirmation_requise.livreur',
        // Administrateurs : fraude et courses bloquées
        'jcode.fraude_gps.admin',
        'securite.contournement_bannissement.admin',
        'livraison.escalade_sans_livreur.admin',
        'livraison.livreur_immobile.admin',
    ];

    public function test_chaque_evenement_est_complet(): void
    {
        foreach (NotificationCatalog::all() as $key => $event) {
            $this->assertMatchesRegularExpression('/^[a-z_]+(\.[a-z_]+)+$/', $key);
            $this->assertLessThanOrEqual(100, strlen($key), $key);

            foreach (['label', 'domain', 'audience', 'type', 'title', 'body', 'sms'] as $field) {
                $this->assertArrayHasKey($field, $event, "{$key} : champ {$field} manquant");
            }

            $this->assertArrayHasKey($event['domain'], NotificationCatalog::DOMAINS, $key);
            $this->assertArrayHasKey($event['audience'], NotificationCatalog::AUDIENCES, $key);
            $this->assertLessThanOrEqual(50, strlen($event['type']), "{$key} : type trop long pour notifications.type");
            $this->assertIsBool($event['sms'], $key);
        }
    }

    public function test_les_variables_declarees_couvrent_exactement_les_textes(): void
    {
        $templates = app(NotificationTemplateService::class);

        foreach (NotificationCatalog::all() as $key => $_) {
            $event = NotificationCatalog::get($key);
            $declared = array_keys($event['variables']);
            $used = array_unique(array_merge(
                $templates->placeholders($event['title']),
                $templates->placeholders($event['body']),
                $event['sms_body'] !== null ? $templates->placeholders($event['sms_body']) : [],
            ));

            sort($declared);
            sort($used);
            $this->assertSame($declared, $used, "{$key} : variables déclarées et utilisées divergent");

            foreach ($event['required'] as $name) {
                $this->assertContains($name, $declared, "{$key} : variable obligatoire {$name} non déclarée");
            }
        }
    }

    public function test_chaque_texte_d_origine_se_rend_sans_accolade_et_tient_en_trois_sms(): void
    {
        $templates = app(NotificationTemplateService::class);

        foreach (NotificationCatalog::all() as $key => $event) {
            // Valeur réaliste : numéro de commande, montant ou nom court.
            $vars = array_map(fn () => '12 500', $event['variables'] ?? []);
            $message = $templates->render($key, $vars);

            foreach (['title', 'body', 'sms'] as $field) {
                $this->assertStringNotContainsString('{', $message[$field], "{$key} : accolade restante dans {$field}");
            }

            if ($event['sms']) {
                $this->assertLessThanOrEqual(
                    NotificationTemplateService::SMS_MAX_SEGMENTS,
                    $templates->smsSegments($message['sms'])['segments'],
                    "{$key} : SMS par défaut trop long"
                );
            }
        }
    }

    public function test_les_sms_par_defaut_suivent_la_decision_metier(): void
    {
        $withSms = array_keys(array_filter(NotificationCatalog::all(), fn (array $event) => $event['sms']));

        $expected = self::EXPECTED_SMS_EVENTS;
        sort($expected);
        sort($withSms);

        $this->assertSame($expected, $withSms);
    }

    public function test_chaque_appel_du_code_designe_un_evenement_existant_et_chaque_evenement_est_emis(): void
    {
        $emitted = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            // Le service lui-même relaie la clé reçue (notifyAdmins → notify).
            if (str_ends_with($file, 'NotificationService.php')) {
                continue;
            }
            $source = file_get_contents($file);
            $offset = 0;

            while (preg_match('/->(notify|notifyAdmins|alertAdminsJuryUnavailable)\(/', $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $start = $match[0][1] + strlen($match[0][0]);
                $call = $this->balancedArguments($source, $start);
                $offset = $start;

                // Relais générique : la clé est choisie par ses appelants, contrôlés ici.
                if (str_starts_with(ltrim($call), '$event,')) {
                    continue;
                }

                preg_match_all("/'([a-z_]+(?:\.[a-z_]+)+)'/", $call, $keys);
                $this->assertNotEmpty($keys[1], basename($file).' : appel sans clé d\'événement littérale');

                foreach ($keys[1] as $key) {
                    $this->assertTrue(NotificationCatalog::has($key), basename($file)." : événement inconnu {$key}");
                    $emitted[$key] = true;
                }
            }
        }

        // Événement « direct » : son texte est tiré par l'expéditeur lui-même
        // (OTP : SmsService, OrangeSmsService, WhatsAppService).
        foreach (NotificationCatalog::all() as $key => $_) {
            if (NotificationCatalog::get($key)['direct']) {
                foreach (['SmsService', 'OrangeSmsService', 'WhatsAppService'] as $sender) {
                    $this->assertStringContainsString("'{$key}'", file_get_contents(app_path("Services/{$sender}.php")), "{$sender} n'utilise pas {$key}");
                }
                $emitted[$key] = true;
            }
        }

        $unused = array_diff(array_keys(NotificationCatalog::all()), array_keys($emitted));
        $this->assertSame([], array_values($unused), 'Événements du catalogue jamais émis');
    }

    public function test_l_ancienne_api_sans_catalogue_a_disparu(): void
    {
        $this->assertFalse(method_exists(NotificationService::class, 'send'));
        $this->assertFalse(method_exists(NotificationService::class, 'sendAdmin'));

        foreach ($this->phpFiles(app_path()) as $file) {
            // Seules exceptions : NotificationService (événements du catalogue)
            // et les campagnes du backoffice (lot D), aux textes libres.
            if (str_ends_with($file, 'NotificationService.php') || str_ends_with($file, 'NotificationCampaignService.php')) {
                continue;
            }
            // Toute notification in-app passe par NotificationService.
            $this->assertStringNotContainsString('Notification::create(', file_get_contents($file), basename($file));
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function balancedArguments(string $source, int $start): string
    {
        $depth = 1;
        $i = $start;
        $length = strlen($source);
        while ($depth > 0 && $i < $length) {
            $char = $source[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $i++;
        }

        return substr($source, $start, $i - $start);
    }
}
