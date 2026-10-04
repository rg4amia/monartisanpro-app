<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\FraudAlert;
use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\RolePermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 28 — gestion des rôles : l'écran ne règle que ce qui agit, les
 * garde-fous portent sur des actions réelles, un administrateur restreint
 * n'exerce pas les actions de l'application, le socle ne s'efface plus.
 */
class Chantier28RolesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /** @param  list<string>  $capabilities */
    private function adminWith(array $capabilities = ['admin.full-access']): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id, 'permission_id' => $permissionId, 'created_at' => now(),
            ]);
        }

        return $admin;
    }

    private function account(string $role): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    // ─── Catalogue : seules les actions qui agissent ───────────────────────

    public function test_les_actions_reglables_sont_celles_qu_une_route_exige(): void
    {
        $effective = RolePermissionService::effectiveActions();

        $this->assertContains('mission.create', $effective);
        $this->assertContains('jcode.scan', $effective);
        // Aucune route ne les exige : elles n'ont aucun effet.
        $this->assertNotContains('orders.create', $effective);
        $this->assertNotContains('transactions.view', $effective);
        $this->assertSame([], array_filter($effective, fn (string $action) => str_starts_with($action, 'admin.')));
    }

    public function test_toute_action_exigee_par_une_route_existe_au_catalogue(): void
    {
        // Une action exigée mais absente du catalogue n'est accordée à aucun
        // rôle : la route répondrait 403 à tout le monde.
        $missing = array_diff(RolePermissionService::effectiveActions(), Permission::pluck('name')->all());

        $this->assertSame([], array_values($missing));
    }

    public function test_les_garde_fous_ne_portent_que_sur_des_actions_qui_agissent(): void
    {
        $effective = RolePermissionService::effectiveActions();

        foreach (RolePermissionService::PROTECTED as $role => $actions) {
            $this->assertSame([], array_values(array_diff($actions, $effective)), "Action indispensable sans effet pour {$role}.");
            $this->assertSame([], array_values(array_diff($actions, RolePermissionService::DEFAULTS[$role])), "Action indispensable hors des droits d'origine de {$role}.");
        }

        $this->assertSame([], array_values(array_diff(array_keys(RolePermissionService::RESERVED), $effective)));
        // Toute action qui agit est réservée aux rôles dont le contrôleur suppose l'identité.
        $this->assertSame([], array_values(array_diff($effective, array_keys(RolePermissionService::RESERVED))));
    }

    public function test_l_ecran_ne_propose_que_les_actions_qui_agissent(): void
    {
        $this->actingAs($this->adminWith())->get('/admin/roles-permissions')
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];
                $names = collect($props['allPermissions'])->pluck('name')->sort()->values()->all();

                $this->assertSame(RolePermissionService::effectiveActions(), $names);
                $this->assertSame([], $props['rolesPermissions']['livreur']);
                $this->assertContains('mission.create', $props['rolesPermissions']['client']);
                $this->assertNotContains('transactions.view', $props['rolesPermissions']['client']);
                $this->assertFalse($props['customizedRoles']['client']);
            });
    }

    public function test_une_action_sans_effet_ne_se_regle_pas(): void
    {
        $admin = $this->adminWith();

        foreach (['assign', 'revoke'] as $verb) {
            $this->actingAs($admin)
                ->postJson("/api/v1/admin/roles-permissions/{$verb}", ['role' => 'client', 'permission' => 'orders.create'])
                ->assertStatus(422)
                ->assertJsonPath('errors.permission.0', RolePermissionService::INEFFECTIVE_MESSAGE);
        }

        $this->assertTrue($this->account('client')->hasPermissionTo('orders.create'));
    }

    // ─── Garde-fous ────────────────────────────────────────────────────────

    public function test_une_action_dont_depend_un_chantier_en_cours_ne_se_retire_pas(): void
    {
        $admin = $this->adminWith();

        foreach (['jalon.upload-photos', 'jalon.request-otp', 'jcode.upload-photo-materials'] as $action) {
            $this->actingAs($admin)
                ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => $action])
                ->assertStatus(422);
        }

        $this->assertTrue($this->account('artisan')->hasPermissionTo('jalon.upload-photos'));
    }

    public function test_creer_une_mission_ne_s_attribue_qu_au_client(): void
    {
        $admin = $this->adminWith();

        foreach (['artisan', 'fournisseur', 'livreur', 'referent'] as $role) {
            $this->actingAs($admin)
                ->postJson('/api/v1/admin/roles-permissions/assign', ['role' => $role, 'permission' => 'mission.create'])
                ->assertStatus(422);
        }
    }

    // ─── Retour aux droits d'origine ───────────────────────────────────────

    public function test_un_role_revient_a_ses_droits_d_origine(): void
    {
        $admin = $this->adminWith();
        $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => 'devis.update'])
            ->assertOk();

        $this->actingAs($admin)->get('/admin/roles-permissions')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('customizedRoles.artisan', true));

        $this->actingAs($admin)
            ->post('/admin/roles-permissions/reset', ['role' => 'artisan'], ['X-Inertia' => 'true'])
            ->assertSessionHas('success');

        $this->assertTrue($this->account('artisan')->hasPermissionTo('devis.update'));
        $this->actingAs($admin)->get('/admin/roles-permissions')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('customizedRoles.artisan', false));

        $log = AdminActivityLog::where('action', 'role_permission.reset')->sole();
        $this->assertSame('artisan', $log->context['role']);
        $this->assertNotContains('devis.update', $log->context['before']);
        $this->assertContains('devis.update', $log->context['after']);
    }

    public function test_un_retour_sans_changement_n_est_pas_audite_et_un_role_inconnu_est_refuse(): void
    {
        $admin = $this->adminWith();

        $this->actingAs($admin)->postJson('/api/v1/admin/roles-permissions/reset', ['role' => 'client'])->assertOk();
        $this->assertSame(0, AdminActivityLog::where('action', 'role_permission.reset')->count());

        $this->actingAs($admin)->postJson('/api/v1/admin/roles-permissions/reset', ['role' => 'admin'])->assertStatus(422);
        $this->actingAs($this->adminWith(['admin.faq.manage']))
            ->postJson('/api/v1/admin/roles-permissions/reset', ['role' => 'client'])->assertForbidden();
    }

    // ─── Socle ─────────────────────────────────────────────────────────────

    public function test_relancer_le_seeder_n_efface_pas_les_reglages(): void
    {
        $this->actingAs($this->adminWith())
            ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => 'devis.update'])
            ->assertOk();

        $this->seed(PermissionSeeder::class);

        $this->assertFalse($this->account('artisan')->hasPermissionTo('devis.update'));
    }

    public function test_le_role_driver_n_a_plus_aucun_droit_en_base(): void
    {
        $this->assertSame(0, DB::table('permission_role')->where('role', 'driver')->count());
        $this->assertArrayNotHasKey('driver', RolePermissionService::DEFAULTS);
        $this->assertSame(RolePermissionService::ROLES, array_keys(RolePermissionService::DEFAULTS));
    }

    // ─── Administrateurs ───────────────────────────────────────────────────

    public function test_un_administrateur_restreint_n_exerce_pas_les_actions_de_l_application(): void
    {
        $restricted = $this->adminWith(['admin.faq.manage']);
        $full = $this->adminWith();

        $this->assertFalse(Gate::forUser($restricted)->allows('devis.accept'));
        $this->assertTrue(Gate::forUser($full)->allows('devis.accept'));
        // Une action qui n'existe pas n'est accordée à personne.
        $this->assertFalse(Gate::forUser($full)->allows('action.inexistante'));
        // Les rôles de l'application ne changent pas.
        $this->assertTrue(Gate::forUser($this->account('client'))->allows('devis.accept'));
        $this->assertFalse(Gate::forUser($this->account('artisan'))->allows('devis.accept'));
    }

    public function test_toute_capacite_du_catalogue_est_installee_et_appliquee(): void
    {
        $catalog = AdminPermissionService::allCapabilityNames();

        // Installée : sans ligne en base, la capacité ne peut pas être accordée.
        $this->assertSame([], array_values(array_diff([...$catalog, AdminPermissionService::FULL_ACCESS], Permission::pluck('name')->all())));

        // Appliquée : par une route, ou par un filtre de données du backoffice.
        $onRoutes = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && preg_match('/^can:(admin\.[a-z.\-]+)$/', $middleware, $match) === 1) {
                    $onRoutes[] = $match[1];
                }
            }
        }
        $inData = ['admin.notifications.view', 'admin.fraud.view'];

        $this->assertSame([], array_values(array_diff($catalog, $onRoutes, $inData)));
    }

    public function test_une_capacite_non_installee_est_refusee_au_lieu_d_etre_ignoree(): void
    {
        $actor = $this->adminWith();
        $target = $this->adminWith(['admin.faq.manage']);
        Permission::where('name', 'admin.kyc.view')->delete();

        $this->actingAs($actor)
            ->post("/admin/admins/{$target->id}/permissions", ['capabilities' => ['admin.kyc.view']])
            ->assertSessionHas('error');

        // Le compte garde ses droits : il ne se retrouve pas sans ligne, donc à accès total.
        $this->assertSame(['admin.faq.manage'], app(AdminPermissionService::class)->capabilitiesFor($target));
    }

    public function test_les_profils_types_ne_contiennent_que_des_capacites_du_catalogue(): void
    {
        $catalog = AdminPermissionService::allCapabilityNames();

        foreach (AdminPermissionService::PROFILES as $key => $profile) {
            $this->assertNotEmpty($profile['label']);
            $this->assertSame([], array_values(array_diff($profile['capabilities'], $catalog)), "Profil {$key}.");
            // Un profil type ne donne jamais la gestion des rôles, qui vaut l'accès total.
            $this->assertNotContains('admin.roles.manage', $profile['capabilities']);
        }

        $this->actingAs($this->adminWith())->get('/admin/roles-permissions')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('adminProfiles.support.capabilities'));
    }

    public function test_la_liste_des_alertes_de_fraude_demande_sa_capacite(): void
    {
        $suspect = $this->account('artisan');
        FraudAlert::create([
            'reference' => 'FR-TEST-1', 'user_id' => $suspect->id, 'type' => 'collusion',
            'severity' => 'haute', 'risk_score' => 80, 'statut' => 'ouverte', 'reasons_json' => [],
        ]);

        $this->actingAs($this->adminWith(['admin.observability.view']))->get('/admin/observability')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('observability.fraud.alerts_list', [])
                ->where('observability.fraud.alerts_hidden', true)
                ->where('observability.fraud.open_alerts_count', 1));

        $this->actingAs($this->adminWith(['admin.observability.view', 'admin.fraud.view']))->get('/admin/observability')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('observability.fraud.alerts_list', 1));
    }
}
