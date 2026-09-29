<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Résolution et rendu des messages de notification (Chantier 14, lot B).
 *
 * Un événement du catalogue peut être surchargé par une ligne de
 * `notification_templates` ; toute colonne vide reprend la valeur du code.
 * Le rendu remplace les variables `{nom}` en une seule passe (aucune
 * évaluation de code) : une valeur contenant elle-même des accolades n'est
 * jamais réinterprétée.
 */
class NotificationTemplateService
{
    private const CACHE_KEY = 'notification_templates.overrides';

    private const CACHE_TTL_SECONDS = 300;

    /** Au-delà de trois segments, un SMS devient coûteux et illisible. */
    public const SMS_MAX_SEGMENTS = 3;

    private const PLACEHOLDER = '/\{([a-z_]+)\}/';

    /**
     * Alphabet GSM 03.38 (table de base) : un SMS qui n'utilise que ces
     * caractères tient en 160 caractères par segment, sinon en 70 (Unicode).
     */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Caractères GSM de la table d'extension : ils comptent double. */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    /**
     * Définition effective d'un événement : catalogue + surcharge éventuelle.
     *
     * @return array{event: string, type: string, title: string, body: string, sms_body: ?string, channels: array{in_app: bool, push: bool, sms: bool}, overridden: bool}
     */
    public function resolve(string $event): array
    {
        $definition = NotificationCatalog::get($event);
        $override = $this->overrides()[$event] ?? null;

        return [
            'event' => $event,
            'type' => $definition['type'],
            'title' => $this->filled($override['push_title'] ?? null) ?? $definition['title'],
            'body' => $this->filled($override['push_body'] ?? null) ?? $definition['body'],
            'sms_body' => $this->filled($override['sms_body'] ?? null) ?? $definition['sms_body'],
            'channels' => [
                'in_app' => $override['channel_in_app'] ?? true,
                'push' => $override['channel_push'] ?? true,
                'sms' => $override['channel_sms'] ?? (bool) $definition['sms'],
            ],
            'overridden' => $override !== null,
        ];
    }

    /**
     * Message prêt à l'envoi.
     *
     * Si un texte personnalisé réclame une variable que l'appelant n'a pas
     * fournie, on retombe sur le texte d'origine plutôt que d'envoyer
     * « {montant} » à un utilisateur (Règle d'or 29).
     *
     * @param  array<string, scalar|null>  $vars
     * @return array{event: string, type: string, title: string, body: string, sms: string, channels: array{in_app: bool, push: bool, sms: bool}}
     */
    public function render(string $event, array $vars = []): array
    {
        $resolved = $this->resolve($event);
        $definition = NotificationCatalog::get($event);
        $vars = array_map(fn ($value) => (string) ($value ?? ''), $vars);

        $texts = [
            'title' => $resolved['title'],
            'body' => $resolved['body'],
            'sms_body' => $resolved['sms_body'],
        ];

        if ($resolved['overridden'] && $this->missingVariables($texts, $vars) !== []) {
            Log::warning('[Notifications] Texte personnalisé inutilisable, texte d\'origine envoyé.', [
                'event' => $event,
                'missing' => $this->missingVariables($texts, $vars),
            ]);
            $texts = ['title' => $definition['title'], 'body' => $definition['body'], 'sms_body' => $definition['sms_body']];
        }

        if (($missing = $this->missingVariables($texts, $vars)) !== []) {
            // Texte d'origine incomplet : erreur de l'appelant, couverte par les
            // tests du catalogue. On n'expose jamais l'accolade brute.
            Log::error('[Notifications] Variable manquante pour un texte d\'origine.', ['event' => $event, 'missing' => $missing]);
        }

        $title = $this->interpolate($texts['title'], $vars);
        $body = $this->interpolate($texts['body'], $vars);
        $sms = $texts['sms_body'] !== null
            ? $this->interpolate($texts['sms_body'], $vars)
            : "{$title}: {$body}";

        return [
            'event' => $event,
            'type' => $resolved['type'],
            'title' => $title,
            'body' => $body,
            'sms' => $sms,
            'channels' => $resolved['channels'],
        ];
    }

    /**
     * Contrôle d'un texte personnalisé avant enregistrement (utilisé par le
     * backoffice, lot C). Renvoie les erreurs par champ, en français.
     *
     * @param  array{push_title?: ?string, push_body?: ?string, sms_body?: ?string, channel_sms?: ?bool}  $input
     * @return array<string, list<string>>
     */
    public function validate(string $event, array $input): array
    {
        $definition = NotificationCatalog::get($event);
        $allowed = array_keys($definition['variables']);
        $errors = [];

        $fields = [
            'push_title' => $this->filled($input['push_title'] ?? null) ?? $definition['title'],
            'push_body' => $this->filled($input['push_body'] ?? null) ?? $definition['body'],
            'sms_body' => $this->filled($input['sms_body'] ?? null),
        ];

        foreach ($fields as $field => $text) {
            if ($text === null) {
                continue;
            }
            foreach ($this->placeholders($text) as $name) {
                if (! in_array($name, $allowed, true)) {
                    $errors[$field][] = "La variable {{$name}} n'existe pas pour ce message.";
                }
            }
        }

        // Une variable obligatoire (code de retrait, montant…) doit rester dans
        // chaque texte où elle figurait à l'origine.
        $originals = ['push_title' => $definition['title'], 'push_body' => $definition['body']];
        foreach ($definition['required'] as $name) {
            foreach ($originals as $field => $original) {
                if (in_array($name, $this->placeholders($original), true)
                    && ! in_array($name, $this->placeholders($fields[$field]), true)) {
                    $errors[$field][] = "La variable {{$name}} est obligatoire dans ce message.";
                }
            }
            if ($fields['sms_body'] !== null && ! in_array($name, $this->placeholders($fields['sms_body']), true)) {
                $errors['sms_body'][] = "La variable {{$name}} est obligatoire dans ce message.";
            }
        }

        $smsText = $fields['sms_body'] ?? "{$fields['push_title']}: {$fields['push_body']}";
        $smsEnabled = $input['channel_sms'] ?? (bool) $definition['sms'];
        if ($smsEnabled && $this->smsSegments($smsText)['segments'] > self::SMS_MAX_SEGMENTS) {
            $errors['sms_body'][] = 'Le SMS dépasse '.self::SMS_MAX_SEGMENTS.' segments : raccourcissez-le.';
        }

        return $errors;
    }

    /**
     * Longueur facturée d'un SMS : encodage, caractères comptés, segments.
     * GSM-7 : 160 caractères (153 par segment au-delà) ; Unicode : 70 (67).
     *
     * @return array{encoding: 'gsm'|'unicode', length: int, segments: int}
     */
    public function smsSegments(string $text): array
    {
        $chars = mb_str_split($text);
        $gsm = true;
        $length = 0;

        foreach ($chars as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $length++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $length += 2;
            } else {
                $gsm = false;
                break;
            }
        }

        if (! $gsm) {
            $length = count($chars);
            $segments = $length <= 70 ? 1 : (int) ceil($length / 67);

            return ['encoding' => 'unicode', 'length' => $length, 'segments' => max(1, $segments)];
        }

        $segments = $length <= 160 ? 1 : (int) ceil($length / 153);

        return ['encoding' => 'gsm', 'length' => $length, 'segments' => max(1, $segments)];
    }

    /**
     * À appeler après toute modification de `notification_templates`.
     */
    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    public function placeholders(string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            // Déploiement en cours (migration pas encore passée) : textes d'origine.
            if (! Schema::hasTable('notification_templates')) {
                return [];
            }

            return NotificationTemplate::query()
                ->get()
                ->keyBy('event_key')
                ->map(fn (NotificationTemplate $template) => $template->only([
                    'push_title', 'push_body', 'sms_body', 'channel_in_app', 'channel_push', 'channel_sms',
                ]))
                ->all();
        });
    }

    /**
     * @param  array<string, ?string>  $texts
     * @param  array<string, string>  $vars
     * @return list<string>
     */
    private function missingVariables(array $texts, array $vars): array
    {
        $missing = [];
        foreach ($texts as $text) {
            if ($text === null) {
                continue;
            }
            foreach ($this->placeholders($text) as $name) {
                if (! array_key_exists($name, $vars)) {
                    $missing[] = $name;
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function interpolate(string $text, array $vars): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $match) => $vars[$match[1]] ?? '',
            $text
        );
    }

    private function filled(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
