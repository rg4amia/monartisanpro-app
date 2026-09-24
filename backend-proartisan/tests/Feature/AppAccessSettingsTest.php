<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réglages d'accès à l'application mobile par espace (AppAccessService) :
 * lecture publique avec valeurs par défaut, écriture réservée à l'admin.
 */
class AppAccessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'block_client' => 'none',
            'block_artisan' => 'new',
            'block_fournisseur' => 'none',
            'block_livreur' => 'all',
            'app_access_disabled_message_livreur' => 'Espace livreur en maintenance.',
        ], $overrides);
    }

    public function test_la_lecture_publique_renvoie_les_neuf_reglages(): void
    {
        $response = $this->getJson('/api/v1/settings/app-access')->assertOk();

        $this->assertCount(9, $response->json('data'));
        $this->assertNotEmpty($response->json('data.app_access_disabled_message_client'));
    }

    public function test_la_lecture_retombe_sur_la_valeur_par_defaut_si_la_cle_manque(): void
    {
        Setting::query()->where('key', 'block_artisan')->delete();

        $this->getJson('/api/v1/settings/app-access')
            ->assertOk()
            ->assertJsonPath('data.block_artisan', 'none');
    }

    public function test_un_admin_met_a_jour_les_reglages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/settings/app-access', $this->payload())
            ->assertOk();

        $this->getJson('/api/v1/settings/app-access')
            ->assertJsonPath('data.block_artisan', 'new')
            ->assertJsonPath('data.block_livreur', 'all')
            ->assertJsonPath('data.app_access_disabled_message_livreur', 'Espace livreur en maintenance.');

        $this->assertSame('app_access', Setting::query()->where('key', 'block_livreur')->value('group'));
    }

    public function test_un_mode_de_blocage_inconnu_est_refuse(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/settings/app-access', $this->payload(['block_client' => 'tous']))
            ->assertStatus(422);
    }

    public function test_un_non_admin_ne_peut_pas_modifier_les_reglages(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($client)
            ->putJson('/api/v1/admin/settings/app-access', $this->payload())
            ->assertForbidden();

        $this->assertNotSame('all', Setting::query()->where('key', 'block_livreur')->value('value'));
    }
}
