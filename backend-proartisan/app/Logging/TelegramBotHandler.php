<?php

namespace App\Logging;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramBotHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $chatId,
        Level|int|string $level = Level::Error,
        bool $bubble = true,
        private readonly string $appName = 'ProsArtisan',
        private readonly string $appEnv = 'production',
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (blank($this->botToken) || blank($this->chatId)) {
            return;
        }

        $context = $record->context;
        $exceptionMessage = '';

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $exceptionMessage = sprintf(
                "%s: %s\n%s:%d",
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            );
        }

        $lines = [
            '<b>ProsArtisan API - Alerte</b>',
            '',
            '<b>App:</b> '.e($this->appName),
            '<b>Env:</b> '.e($this->appEnv),
            '<b>Niveau:</b> '.e($record->level->getName()),
            '<b>Message:</b>',
            '<code>'.e($this->truncate((string) $record->message, 1200)).'</code>',
        ];

        if ($exceptionMessage !== '') {
            $lines[] = '<b>Exception:</b>';
            $lines[] = '<code>'.e($this->truncate($exceptionMessage, 1200)).'</code>';
        }

        $contextData = array_filter($context, fn($key) => $key !== 'exception', ARRAY_FILTER_USE_KEY);
        if (! empty($contextData)) {
            $lines[] = '<b>Contexte:</b>';
            $lines[] = '<code>'.e($this->truncate(json_encode($contextData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 1200)).'</code>';
        }

        $lines[] = '<b>Heure UTC:</b> '.e(now('UTC')->toDateTimeString());

        try {
            Http::asForm()
                ->timeout(3)
                ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $this->truncate(implode("\n", $lines), 3500),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Throwable) {
            // Évite les boucles de logs en cas d'échec Telegram.
        }
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength).'... [tronque]';
    }
}
