<?php

namespace Tests;

use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> Appels extérieurs bloqués puis avalés par un repli. */
    private array $strayRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Le store `array` persiste au sein du process PHPUnit : on repart propre
        // pour éviter qu'un cache de KPI (Chantier C4) pollue le test suivant.
        Cache::flush();

        // Les envois push/SMS partent après la réponse HTTP en production
        // (dispatchAfterResponse). En test, un appel de service n'est suivi
        // d'aucune réponse et les rappels de fin de requête ne sont pas vidés
        // entre deux requêtes : on exécute donc ces envois immédiatement.
        app(Dispatcher::class)->withoutDispatchingAfterResponses();

        // Aucun test ne doit joindre un service extérieur (Gemini, Yandex,
        // OSRM, SMS, paiement) : un appel non simulé lève une exception au
        // lieu de partir sur le réseau, où il ralentit la suite et rend son
        // résultat dépendant de la connexion.
        Http::preventStrayRequests();

        // Beaucoup de services attrapent cette exception et se replient
        // (distance à vol d'oiseau, push ignoré) : le test passerait sans que
        // l'appel soit jamais simulé. On relève donc ces replis dans le journal
        // pour faire échouer le test à sa clôture.
        $this->strayRequests = [];
        Log::listen(function ($entry) {
            if (is_string($entry->message) && str_contains($entry->message, 'without a matching fake')) {
                $this->strayRequests[] = $entry->message;
            }
        });
    }

    protected function tearDown(): void
    {
        $strays = $this->strayRequests;

        parent::tearDown();

        if ($strays !== []) {
            $this->fail('Appel extérieur non simulé (Http::fake, ou Tests\Support\Routing::fakeOsrm()) :'.PHP_EOL.implode(PHP_EOL, array_unique($strays)));
        }
    }
}
