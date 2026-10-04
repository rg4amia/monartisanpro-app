<?php

namespace Tests\Feature\Admin;

use App\Models\Litige;
use App\Models\Mission;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminUserService;
use App\Services\RolePermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 29 — suites de l'analyse « Gestion des rôles » du 04/10/2026 :
 * nul n'accorde ce qu'il ne détient pas (B6), les libellés des actions
 * viennent du code (C2), le tableau de bord ne sert que ce qu'il affiche.
 */
class Chantier29RightsFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /** @param  list<string>  $capabilities */
    private function adminWith(array $capabilities): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id, 'permission_id' => $permissionId, 'created_at' => now(),
            ]);
        }

        return $admin;
    }

    /** @return list<string> */
    private function stored(User $admin): array
    {
        return DB::table('admin_permission_user')
            ->join('permissions', 'admin_permission_user.permission_id', '=', 'permissions.id')
            ->where('user_id', $admin->id)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    /** @param  list<string>  $capabilities */
    private function sync(User $actor, User $target, array $capabilities): TestResponse
    {
        return $this->actingAs($actor)->post("/admin/admins/{$target->id}/permissions", ['capabilities' => $capabilities]);
    }

    // ─── Nul n'accorde ce qu'il ne détient pas ─────────────────────────────

    public function test_un_administrateur_restreint_n_accorde_pas_un_droit_qu_il_ne_detient_pas(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view', 'admin.faq.manage']);
        $target = $this->adminWith(['admin.faq.manage']);

        $this->sync($actor, $target, ['admin.faq.manage', 'admin.transactions.manage'])->assertSessionHas('error');
        $this->assertSame(['admin.faq.manage'], $this->stored($target));

        $this->sync($actor, $target, ['admin.full-access'])->assertSessionHas('error');
        $this->assertSame(['admin.faq.manage'], $this->stored($target));
    }

    public function test_il_accorde_et_retire_les_droits_qu_il_detient(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view', 'admin.faq.manage']);
        $target = $this->adminWith(['admin.faq.manage']);

        $this->sync($actor, $target, ['admin.users.view'])->assertSessionHas('success');

        $this->assertSame(['admin.users.view'], $this->stored($target));
    }

    public function test_il_ne_s_accorde_pas_un_droit_de_plus(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view']);

        $this->sync($actor, $actor, ['admin.roles.manage', 'admin.users.view', 'admin.transactions.manage'])->assertSessionHas('error');
        $this->sync($actor, $actor, ['admin.full-access'])->assertSessionHas('error');

        $this->assertSame(['admin.roles.manage', 'admin.users.view'], $this->stored($actor));
    }

    public function test_il_ne_modifie_pas_les_droits_d_un_compte_plus_etendu_que_le_sien(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view']);
        $full = $this->adminWith(['admin.full-access']);
        $wider = $this->adminWith(['admin.users.view', 'admin.transactions.view']);

        $this->sync($actor, $full, ['admin.users.view'])->assertSessionHas('error', AdminPermissionService::OUTRANKED_MESSAGE);
        $this->sync($actor, $wider, ['admin.users.view'])->assertSessionHas('error', AdminPermissionService::OUTRANKED_MESSAGE);

        $this->assertSame(['admin.full-access'], $this->stored($full));
        $this->assertSame(['admin.transactions.view', 'admin.users.view'], $this->stored($wider));
    }

    public function test_un_administrateur_a_acces_total_accorde_tout(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.faq.manage']);

        $this->sync($actor, $target, ['admin.full-access'])->assertSessionHas('success');

        $this->assertSame(['admin.full-access'], $this->stored($target));
    }

    public function test_l_administrateur_cree_par_un_administrateur_restreint_recoit_ses_droits_et_non_l_acces_total(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view', 'admin.users.manage']);
        $this->actingAs($actor);

        $created = app(AdminUserService::class)->create([
            'name' => 'Nouvel Admin',
            'email' => 'nouvel.admin@example.test',
            'phone' => '+2250700009901',
            'role' => 'admin',
            'password' => 'mot-de-passe-long',
        ]);

        $this->assertSame(['admin.roles.manage', 'admin.users.manage', 'admin.users.view'], $this->stored($created));
    }

    public function test_un_administrateur_restreint_ne_modifie_pas_le_compte_d_un_administrateur_plus_etendu(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view', 'admin.users.manage']);
        $full = $this->adminWith(['admin.full-access']);
        $this->actingAs($actor);

        try {
            app(AdminUserService::class)->update($full, ['name' => 'Compte repris']);
            $this->fail('La modification aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertSame(AdminPermissionService::OUTRANKED_MESSAGE, $e->validator->errors()->first());
        }

        $this->assertNotSame('Compte repris', $full->fresh()->name);
    }

    public function test_l_ecran_annonce_les_droits_accordables_et_les_comptes_hors_de_portee(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view']);
        $full = $this->adminWith(['admin.full-access']);
        $narrow = $this->adminWith(['admin.users.view']);

        $this->actingAs($actor)->get('/admin/roles-permissions')->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($actor, $full, $narrow) {
                $props = $page->toArray()['props'];
                $admins = collect($props['admins'])->keyBy('id');

                $this->assertEqualsCanonicalizing(['admin.roles.manage', 'admin.users.view'], $props['grantableCapabilities']);
                $this->assertTrue($admins[$actor->id]['editable']);
                $this->assertTrue($admins[$narrow->id]['editable']);
                $this->assertFalse($admins[$full->id]['editable']);
                $this->assertSame(['*'], $admins[$full->id]['capabilities']);
                $this->assertSame(['admin.users.view'], $admins[$narrow->id]['capabilities']);
            });
    }

    // ─── Libellés des actions ──────────────────────────────────────────────

    public function test_le_libelle_d_une_action_vient_du_code_et_non_de_la_base(): void
    {
        Permission::where('name', 'mission.create')->update(['description' => 'Ancien libellé en base', 'category' => 'autre']);

        $action = app(RolePermissionService::class)->getAllPermissions()->firstWhere('name', 'mission.create');

        $this->assertSame(RolePermissionService::CATALOG['mission.create'][1], $action['description']);
        $this->assertSame('missions', $action['category']);
    }

    public function test_toute_action_reglable_ou_d_origine_figure_au_catalogue_du_code(): void
    {
        $known = array_keys(RolePermissionService::CATALOG);

        $this->assertSame([], array_values(array_diff(RolePermissionService::effectiveActions(), $known)));

        foreach (RolePermissionService::DEFAULTS as $role => $actions) {
            $this->assertSame([], array_values(array_diff($actions, $known)), "Rôle {$role}");
        }

        foreach (RolePermissionService::CATALOG as $name => [$category, $label]) {
            $this->assertNotSame('', trim($category), $name);
            $this->assertNotSame('', trim($label), $name);
        }
    }

    // ─── Tableau de bord ───────────────────────────────────────────────────

    public function test_le_tableau_de_bord_ne_sert_que_les_champs_qu_il_affiche(): void
    {
        $mission = Mission::create([
            'client_id' => User::factory()->create(['role' => 'client', 'name' => 'Awa Cliente'])->id,
            'artisan_id' => User::factory()->create(['role' => 'artisan'])->id,
            'description' => 'Fuite sous évier',
            'status' => 'in_progress',
            'montant_total' => 3_000_000,
            'montant_materiaux' => 1_800_000,
            'montant_mo' => 1_200_000,
            'ratio_materiaux' => 0.60,
        ]);
        $admin = $this->adminWith(['admin.full-access']);
        Litige::create([
            'mission_id' => $mission->id, 'declencheur_id' => $admin->id, 'type' => 'client',
            'description' => 'Travail non conforme', 'statut' => 'ouvert',
        ]);
        Transaction::create([
            'type' => 'acompte', 'montant' => 50000, 'wallet_source' => 'client_wallet', 'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave', 'statut' => 'confirme', 'reference_externe' => 'REF-C29',
        ]);

        $props = [];
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        $this->assertSame(['id', 'mission_id', 'statut', 'created_at', 'mission'], array_keys($props['litiges'][0]));
        $this->assertSame(['montant_total' => 3_000_000], $props['litiges'][0]['mission']);
        $this->assertSame(['id' => $mission->id, 'status' => 'in_progress'], $props['missions'][0]);
        $this->assertSame(['id', 'type', 'statut', 'montant', 'created_at'], array_keys($props['transactions'][0]));
        $this->assertSame('confirme', $props['transactions'][0]['statut']);
        $this->assertSame(50000, $props['transactions'][0]['montant']);

        // Listes que le tableau de bord n'affichait pas.
        foreach (['orders', 'evaluationsList', 'artisansScores'] as $unused) {
            $this->assertArrayNotHasKey($unused, $props);
        }
    }
}
