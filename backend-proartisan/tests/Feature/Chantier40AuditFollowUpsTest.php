<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 40 — suites de l'audit du 06/10/2026 sur l'état du projet après le
 * Chantier 39.
 */
class Chantier40AuditFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    // ── Anomalie 2 : origines autorisées à appeler l'API ────────────────────

    public function test_le_site_public_appelle_l_api_depuis_chacune_de_ses_adresses(): void
    {
        foreach (['https://prosartisan.net', 'https://www.prosartisan.net', 'https://www.prosartisan.ci'] as $origin) {
            $this->withHeaders(['Origin' => $origin])
                ->getJson('/api/v1/settings/app-access')
                ->assertOk()
                ->assertHeader('Access-Control-Allow-Origin', $origin);

            $this->call('OPTIONS', '/api/v1/vitrine/whatsapp-click', [], [], [], [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ])->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_une_origine_etrangere_n_est_pas_autorisee(): void
    {
        foreach (['https://evil.example', 'https://www.prosartisan.net.evil.example', 'http://www.prosartisan.net'] as $origin) {
            $response = $this->withHeaders(['Origin' => $origin])->getJson('/api/v1/settings/app-access');

            $this->assertNull($response->headers->get('Access-Control-Allow-Origin'), $origin);
        }
    }
}
