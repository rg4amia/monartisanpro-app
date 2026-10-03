<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 18, lot A — la session du backoffice se ferme après 15 minutes
 * d'inactivité et l'administrateur doit se reconnecter.
 */
class AdminIdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_une_session_inactive_depuis_plus_de_15_minutes_est_fermee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $this->travel(16)->minutes();

        $this->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Votre session a été fermée après 15 minutes d\'inactivité. Reconnectez-vous.');

        $this->assertGuest('web');

        $log = AdminActivityLog::where('action', 'admin.session.expired_idle')->sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('serveur', $log->context['origine']);
        $this->assertSame(15, $log->context['delai_minutes']);
    }

    public function test_le_backoffice_reste_inaccessible_apres_la_fermeture(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
        $this->travel(16)->minutes();
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));

        $this->get('/admin/users')->assertRedirect(route('admin.login'));
    }

    public function test_chaque_activite_fait_repartir_le_delai(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();

        $this->travel(14)->minutes();
        $this->get('/admin/dashboard')->assertOk();

        // 28 minutes après la connexion, mais 14 seulement après la dernière activité.
        $this->travel(14)->minutes();
        $this->get('/admin/dashboard')->assertOk();

        $this->assertAuthenticated('web');
        $this->assertSame(0, AdminActivityLog::where('action', 'admin.session.expired_idle')->count());
    }

    public function test_le_delai_se_regle_par_la_configuration(): void
    {
        config(['prosartisan.admin.idle_timeout_minutes' => 5]);

        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
        $this->travel(6)->minutes();

        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_un_appel_json_expire_recoit_un_401(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
        $this->travel(16)->minutes();

        $this->getJson('/admin/cartographie/stats')
            ->assertUnauthorized()
            ->assertJsonPath('redirect', route('admin.login'));

        $this->assertGuest('web');
    }

    public function test_une_navigation_inertia_expiree_renvoie_a_la_connexion(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
        $this->travel(16)->minutes();

        $this->get('/admin/users', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('admin.login'));

        $this->assertGuest('web');
    }

    public function test_une_requete_d_arriere_plan_ne_prolonge_pas_la_session(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();

        $this->travel(10)->minutes();
        $this->get('/admin/dashboard', ['X-Admin-Passive' => '1'])->assertOk();

        $this->travel(6)->minutes();
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_le_maintien_de_session_fait_repartir_le_delai(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();

        $this->travel(10)->minutes();
        $this->postJson('/admin/session/keep-alive')
            ->assertOk()
            ->assertJsonPath('timeout_seconds', 900);

        $this->travel(10)->minutes();
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_l_ecran_peut_fermer_la_session_au_delai_atteint(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $this->postJson('/admin/session/expire')
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.login'));

        $this->assertGuest('web');
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));

        $log = AdminActivityLog::where('action', 'admin.session.expired_idle')->sole();
        $this->assertSame('ecran', $log->context['origine']);
    }

    public function test_la_page_de_connexion_affiche_le_motif_de_la_fermeture(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
        $this->travel(16)->minutes();
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/auth/login')
                ->where('flash.error', 'Votre session a été fermée après 15 minutes d\'inactivité. Reconnectez-vous.')
            );
    }

    public function test_une_session_usurpee_expire_aussi_sans_retour_au_compte_admin(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->withSession([ImpersonationController::SESSION_KEY => $admin->id])
            ->postJson('/admin/session/keep-alive')
            ->assertOk();

        $this->travel(16)->minutes();

        $this->post('/admin/stop-impersonating')->assertRedirect(route('admin.login'));
        $this->assertGuest('web');

        $log = AdminActivityLog::where('action', 'admin.session.expired_idle')->sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($client->id, $log->subject_id);
        $this->assertTrue($log->context['usurpation']);
    }

    public function test_un_appel_api_du_backoffice_par_la_session_expire_aussi(): void
    {
        // La carte de la flotte appelle `/api/v1` avec le cookie de session du backoffice.
        $stateful = ['Referer' => 'http://localhost', 'Accept' => 'application/json'];

        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();

        $this->travel(16)->minutes();

        $this->get('/api/v1/deliveries/fleet-map', $stateful)->assertUnauthorized();
        $this->assertFalse(auth('web')->check());
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_un_jeton_mobile_n_est_pas_soumis_au_delai(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/deliveries/fleet-map')->assertOk();
        $this->travel(16)->minutes();
        $this->withToken($token)->getJson('/api/v1/deliveries/fleet-map')->assertOk();
    }

    public function test_le_delai_est_transmis_a_l_ecran_du_backoffice(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertInertia(fn ($page) => $page->where('auth.idleTimeoutSeconds', 900));
    }

    public function test_un_compte_non_administrateur_n_est_pas_concerne(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->postJson('/admin/session/keep-alive')->assertOk();
        $this->travel(16)->minutes();
        $this->postJson('/admin/session/keep-alive')->assertOk();

        $this->assertAuthenticatedAs($client, 'web');
    }
}
