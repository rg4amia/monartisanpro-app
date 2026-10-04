<?php

namespace Tests\Feature\Admin;

use App\Models\Notification;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Analyse « Gestion des rôles » du 04/10/2026, constats B1 et B2 : le tableau
 * de bord et le centre de notifications servaient à tout administrateur des
 * données que ses capacités lui refusaient ailleurs.
 */
class RestrictedAdminDataLeaksTest extends TestCase
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

    private function clientNotification(array $attributes = []): Notification
    {
        $client = User::factory()->create(['role' => 'client', 'name' => 'Awa Cliente']);

        return Notification::create([
            'user_id' => $client->id,
            'type' => 'payment',
            'title' => 'Information',
            'body' => 'Votre commande est en route.',
            'data_json' => ['order_id' => 12],
            ...$attributes,
        ]);
    }

    /** @return array<string, mixed> */
    private function props(User $admin, string $url): array
    {
        $props = [];
        $this->actingAs($admin)->get($url)->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    // ─── Tableau de bord ───────────────────────────────────────────────────

    public function test_le_tableau_de_bord_ne_sert_a_un_administrateur_restreint_que_les_blocs_de_ses_capacites(): void
    {
        User::factory()->count(3)->create(['role' => 'client']);

        $props = $this->props($this->adminWith(['admin.faq.manage']), '/admin/dashboard');

        foreach (['users', 'transactions', 'missions', 'orders', 'litiges', 'kycUsers', 'fournisseurs', 'evaluationsList', 'artisansScores', 'financialKpis'] as $block) {
            $this->assertSame([], $props[$block], "Le bloc « {$block} » ne doit pas être servi.");
        }
        // Le bloc de sa capacité, lui, est servi.
        $this->assertArrayHasKey('total', $props['faqStats']);
    }

    public function test_chaque_bloc_du_tableau_de_bord_suit_sa_capacite(): void
    {
        User::factory()->count(3)->create(['role' => 'client']);

        $props = $this->props($this->adminWith(['admin.users.view']), '/admin/dashboard');

        $this->assertNotEmpty($props['users']);
        $this->assertSame([], $props['financialKpis']);
        $this->assertSame([], $props['transactions']);

        $props = $this->props($this->adminWith(['admin.transactions.view']), '/admin/dashboard');

        $this->assertArrayHasKey('solde_general', $props['financialKpis']);
        $this->assertSame([], $props['users']);
    }

    public function test_un_administrateur_a_acces_total_recoit_le_tableau_de_bord_entier(): void
    {
        User::factory()->count(3)->create(['role' => 'client']);

        $props = $this->props($this->adminWith(['admin.full-access']), '/admin/dashboard');

        $this->assertNotEmpty($props['users']);
        $this->assertArrayHasKey('solde_general', $props['financialKpis']);
    }

    // ─── Centre de notifications ───────────────────────────────────────────

    public function test_l_historique_des_notifications_est_reserve_a_sa_capacite(): void
    {
        $this->clientNotification();

        $props = $this->props($this->adminWith(['admin.faq.manage']), '/admin/notifications');
        $this->assertNull($props['allNotifications']);
        $this->assertFalse($props['canViewNotificationHistory']);

        $props = $this->props($this->adminWith(['admin.notifications.view']), '/admin/notifications');
        $this->assertTrue($props['canViewNotificationHistory']);
        $this->assertSame('Votre commande est en route.', $props['allNotifications']['data'][0]['body']);
        $this->assertSame('Awa Cliente', $props['allNotifications']['data'][0]['user']['name']);
    }

    public function test_une_notification_portant_un_code_n_affiche_ni_son_texte_ni_ses_donnees(): void
    {
        // Événement du catalogue dont une variable est un code.
        $this->clientNotification([
            'event_key' => 'commande.prete_retrait.client',
            'title' => 'Commande prête',
            'body' => 'Votre commande #12 est prête. Code de retrait : 4821.',
            'data_json' => ['order_id' => 12, 'pickup_code' => '4821'],
        ]);
        // Notification antérieure au catalogue.
        $this->clientNotification(['title' => 'Ancienne', 'body' => 'Votre code de réception est 9917.']);

        $props = $this->props($this->adminWith(['admin.notifications.view']), '/admin/notifications');

        foreach ($props['allNotifications']['data'] as $row) {
            $this->assertTrue($row['masked']);
            $this->assertNull($row['data_json']);
            $this->assertStringNotContainsString('4821', json_encode($row));
            $this->assertStringNotContainsString('9917', json_encode($row));
        }

        // La recherche ne porte plus sur le texte : elle ne retrouve pas un code.
        $props = $this->props($this->adminWith(['admin.notifications.view']), '/admin/notifications?search_notification=4821');
        $this->assertSame([], $props['allNotifications']['data']);
    }

    public function test_un_administrateur_ne_marque_comme_lues_que_ses_propres_notifications(): void
    {
        $admin = $this->adminWith(['admin.faq.manage']);
        $foreign = $this->clientNotification();
        $own = Notification::create(['user_id' => $admin->id, 'type' => 'admin_alert', 'title' => 'Alerte', 'body' => 'Texte', 'data_json' => []]);

        $this->actingAs($admin)->post("/admin/notifications/{$foreign->id}/read")->assertForbidden();
        $this->assertNull($foreign->fresh()->read_at);

        $this->actingAs($admin)->post("/admin/notifications/{$own->id}/read")->assertRedirect();
        $this->assertNotNull($own->fresh()->read_at);
    }
}
