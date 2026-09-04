<?php

namespace Tests;

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
    }
}
