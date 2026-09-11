<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndMissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_mission_lifecycle_simulation(): void
    {
        Storage::fake('public');

        $code = \Illuminate\Support\Facades\Artisan::call('prosartisan:simulate-lifecycle');
        $output = \Illuminate\Support\Facades\Artisan::output();
        fwrite(STDERR, "\n--- COMMAND OUTPUT ---\n" . $output . "\n----------------------\n");
        $this->assertSame(0, $code);
    }
}
