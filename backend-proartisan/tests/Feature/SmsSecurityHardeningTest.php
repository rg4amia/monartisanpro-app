<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Services\AntiBotService;
use App\Services\SmsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Trois défauts relevés le 08/10/2026 en testant l'envoi du code de connexion :
 * le défi anti-robot renvoyait sa propre réponse, un jeton SMS Pro servait de
 * valeur de secours dans le code, et le diagnostic de connectivité SMS était
 * ouvert à tous.
 */
class SmsSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    private function adminWith(array $capabilities): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    public function test_le_defi_anti_robot_ne_renvoie_jamais_sa_reponse(): void
    {
        $this->assertArrayNotHasKey('answer', app(AntiBotService::class)->generateChallenge('send_otp'));

        $response = $this->getJson('/api/v1/auth/security-challenge?action=send_otp')->assertOk();

        $this->assertArrayNotHasKey('answer', $response->json('challenge'));
        $this->assertArrayNotHasKey('answer', $response->json('data'));
        $this->assertNotEmpty($response->json('challenge.token'));
        $this->assertNotEmpty($response->json('challenge.question'));
    }

    public function test_aucun_jeton_sms_n_est_ecrit_dans_la_configuration(): void
    {
        $source = file_get_contents(config_path('services.php'));

        // Un jeton SMS Pro a la forme « <identifiant>|<secret> ».
        $this->assertDoesNotMatchRegularExpression('/[\'"]\d+\|[A-Za-z0-9]{20,}[\'"]/', $source);
    }

    public function test_sans_jeton_configure_aucun_sms_ne_part(): void
    {
        config([
            'services.sms.provider' => 'smspro',
            'services.sms.api_token' => null,
            'services.sms.base_url' => 'https://app.smspro.africa/api/v3',
        ]);
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        $result = app(SmsService::class)->sendOtp('+2250700000001', '1234');

        $this->assertSame('error', $result['status']);
        Http::assertNothingSent();
    }

    public function test_le_diagnostic_sms_n_est_plus_une_route_publique(): void
    {
        Http::fake();

        $this->getJson('/api/v1/settings/sms-diagnostics')->assertNotFound();
        $this->getJson('/admin/observability/sms-diagnostics')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_le_diagnostic_sms_exige_la_capacite_d_observabilite(): void
    {
        Http::fake();

        $this->actingAs($this->adminWith(['admin.faq.manage']))
            ->getJson('/admin/observability/sms-diagnostics')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_le_diagnostic_sms_repond_a_l_administrateur_sans_reveler_le_jeton(): void
    {
        config([
            'services.sms.api_token' => 'jeton-secret-de-test',
            'services.sms.base_url' => 'https://app.smspro.africa/api/v3',
        ]);
        Http::fake([
            'api.ipify.org*' => Http::response('203.0.113.7', 200),
            'app.smspro.africa/*' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $response = $this->actingAs($this->adminWith(['admin.observability.view']))
            ->getJson('/admin/observability/sms-diagnostics')
            ->assertOk()
            ->assertJsonPath('server_outbound_ip', '203.0.113.7')
            ->assertJsonPath('ipv4_test.success', true)
            ->assertJsonPath('ipv4_test.http_code', 401)
            ->assertJsonPath('token_configured', true);

        $this->assertArrayNotHasKey('token_length', $response->json());
        $this->assertStringNotContainsString('jeton-secret-de-test', $response->getContent());
    }
}
