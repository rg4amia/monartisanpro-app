<?php

namespace Tests;

use Illuminate\Bus\Dispatcher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
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
    }
}
