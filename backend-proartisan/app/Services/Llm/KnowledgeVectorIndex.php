<?php

namespace App\Services\Llm;

use App\Services\AiMonitoringService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Index vectoriel facultatif (Qdrant) des fiches publiées. Sans adresse Qdrant
 * ni clé Gemini, tout est inactif et la recherche se fait par mots-clés.
 * Chaque opération est best-effort : son échec ne bloque jamais la publication.
 */
class KnowledgeVectorIndex
{
    public function enabled(): bool
    {
        return ! empty(config('services.qdrant.url')) && ! empty(config('services.gemini.api_key'));
    }

    /**
     * Fiches les plus proches d'un texte, ou `null` si l'index n'a pas répondu.
     *
     * @return list<array<string, mixed>>|null
     */
    public function search(string $text, int $limit = 3): ?array
    {
        if (! $this->enabled() || trim($text) === '') {
            return null;
        }

        $vector = $this->embed($text);
        if ($vector === null) {
            return null;
        }

        $response = $this->request('post', 'points/search', [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ]);

        if ($response === null) {
            return null;
        }

        $matches = [];
        foreach ($response['result'] ?? [] as $point) {
            if (! empty($point['payload']) && is_array($point['payload'])) {
                $matches[] = $point['payload'];
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function upsert(string $id, array $sheet): void
    {
        if (! $this->enabled()) {
            return;
        }

        $alt = $sheet['alternative_prosartisan'] ?? [];
        $text = trim(implode("\n", [
            $alt['titre_vulgarise'] ?? '',
            $alt['methode_execution'] ?? '',
            implode(' ', $sheet['metadata']['tags_pathologies'] ?? []),
        ]));

        $vector = $this->embed($text);
        if ($vector === null) {
            return;
        }

        $this->request('put', 'points', [
            'points' => [['id' => $this->pointId($id), 'vector' => $vector, 'payload' => $sheet]],
        ]);
    }

    public function delete(string $id): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->request('post', 'points/delete', ['points' => [$this->pointId($id)]]);
    }

    /** Qdrant n'accepte qu'un entier ou un UUID : identifiant dérivé de celui de la fiche. */
    private function pointId(string $id): string
    {
        $hash = md5($id);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    /**
     * @return list<float>|null
     */
    private function embed(string $text): ?array
    {
        $model = config('services.gemini.embedding_model', 'text-embedding-004');
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $startTime = microtime(true);

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->post("{$baseUrl}/v1beta/models/{$model}:embedContent?key=".config('services.gemini.api_key'), [
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                ]);

            $elapsed = (microtime(true) - $startTime) * 1000;

            if (! $response->successful()) {
                AiMonitoringService::log("models/{$model}", 'embedding', 0, 0, $elapsed, $response->status(), $response->body());

                return null;
            }

            AiMonitoringService::log("models/{$model}", 'embedding', (int) ceil(strlen($text) / 4), 0, $elapsed, 200);
            $values = $response->json('embedding.values');

            return is_array($values) ? $values : null;
        } catch (\Throwable $e) {
            AiMonitoringService::log("models/{$model}", 'embedding', 0, 0, (microtime(true) - $startTime) * 1000, 500, $e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $path, array $body): ?array
    {
        $url = rtrim((string) config('services.qdrant.url'), '/');
        $collection = config('services.qdrant.collection', 'btp_rules');
        $apiKey = config('services.qdrant.api_key');
        $startTime = microtime(true);

        try {
            $http = Http::timeout(5);
            if (! empty($apiKey)) {
                $http = $http->withHeaders(['api-key' => $apiKey]);
            }

            $response = $http->{$method}("{$url}/collections/{$collection}/{$path}", $body);
            $elapsed = (microtime(true) - $startTime) * 1000;

            if (! $response->successful()) {
                AiMonitoringService::log('qdrant', 'search', 0, 0, $elapsed, $response->status(), $response->body());

                return null;
            }

            AiMonitoringService::log('qdrant', 'search', 0, 0, $elapsed, 200);

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            AiMonitoringService::log('qdrant', 'search', 0, 0, (microtime(true) - $startTime) * 1000, 500, $e->getMessage());
            Log::warning('Index vectoriel des fiches injoignable', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
