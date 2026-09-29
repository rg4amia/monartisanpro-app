<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\NotificationTemplateAdminService;
use App\Services\Notifications\NotificationCatalog;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 14, lot C — onglet « Messages push & SMS » du backoffice.
 */
class NotificationMessagesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.rest_api_key' => 'test-rest-key',
            'services.sms.provider' => 'log',
        ]);
        Http::fake(['onesignal.com/*' => Http::response(['id' => 'x'], 200)]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /** @param list<string> $capabilities */
    private function restrictedAdmin(array $capabilities): User
    {
        $admin = $this->admin();
        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert(['user_id' => $admin->id, 'permission_id' => $permissionId, 'created_at' => now()]);
        }

        return $admin;
    }

    public function test_l_onglet_liste_tout_le_catalogue_avec_l_otp_verrouille(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/messages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/notification-messages')
                ->has('notificationEvents', count(NotificationCatalog::all()))
                ->where('notificationSmsMaxSegments', 3)
                ->has('notificationDeliveries.data')
                ->has('notificationDeliveryStats'));

        $otp = collect($this->app->make(NotificationTemplateAdminService::class)->events())->firstWhere('key', 'auth.otp');
        $this->assertEquals((object) ['in_app' => false, 'push' => false, 'sms' => true], $otp['locked']);
        $this->assertTrue($otp['security']);
    }

    public function test_la_capacite_admin_notifications_manage_est_exigee(): void
    {
        $this->assertTrue(Permission::where('name', 'admin.notifications.manage')->exists());

        $this->actingAs($this->restrictedAdmin(['admin.faq.manage']))->get('/admin/messages')->assertForbidden();
        $this->actingAs($this->restrictedAdmin(['admin.faq.manage']))->put('/admin/messages/jalon.paye.artisan', ['push_title' => 'X'])->assertForbidden();
        $this->assertSame(0, NotificationTemplate::count());

        $this->actingAs($this->restrictedAdmin(['admin.notifications.manage']))->get('/admin/messages')->assertOk();
    }

    public function test_un_message_modifie_est_enregistre_audite_et_envoye(): void
    {
        $admin = $this->admin();
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->put('/admin/messages/jalon.paye.artisan', [
                'push_title' => 'Étape {etape} payée',
                // Identique à l'origine : non stocké.
                'push_body' => 'Le jalon #{etape} a été validé. Paiement en cours.',
                'sms_body' => '',
                'channel_in_app' => true,
                'channel_push' => true,
                'channel_sms' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $template = NotificationTemplate::where('event_key', 'jalon.paye.artisan')->sole();
        $this->assertSame('Étape {etape} payée', $template->push_title);
        $this->assertNull($template->push_body);
        $this->assertNull($template->sms_body);
        $this->assertFalse($template->channel_sms);
        $this->assertNull($template->channel_push);
        $this->assertSame($admin->id, $template->updated_by);

        $log = AdminActivityLog::where('action', 'notification_template.updated')->sole();
        $this->assertSame('Paiement reçu !', $log->context['avant']['titre']);
        $this->assertSame('Étape {etape} payée', $log->context['apres']['titre']);
        $this->assertFalse($log->context['apres']['canaux']['sms']);

        app(NotificationService::class)->notify($artisan, 'jalon.paye.artisan', ['etape' => 4]);
        $this->assertSame('Étape 4 payée', Notification::where('user_id', $artisan->id)->sole()->title);
        $this->assertSame(0, NotificationDelivery::where('channel', 'sms')->count());
    }

    public function test_un_texte_invalide_est_refuse(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/messages/commande.prete_retrait.client', [
                'push_body' => 'Votre commande {commande} est prête pour {client}.',
            ])
            ->assertSessionHasErrors(['push_body']);

        $this->assertSame(0, NotificationTemplate::count());
    }

    public function test_l_otp_reste_un_sms_et_garde_son_code(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/messages/auth.otp', ['channel_sms' => false])->assertSessionHasErrors(['channels']);
        $this->actingAs($admin)->put('/admin/messages/auth.otp', ['channel_push' => true])->assertSessionHasErrors(['channels']);
        $this->actingAs($admin)->put('/admin/messages/auth.otp', ['sms_body' => 'Votre code ProsArtisan, valide {minutes} min.'])->assertSessionHasErrors(['sms_body']);
        $this->assertSame(0, NotificationTemplate::count());

        $this->actingAs($admin)
            ->put('/admin/messages/auth.otp', ['sms_body' => 'ProsArtisan : votre code est {code} ({minutes} min). Ne le donnez à personne.'])
            ->assertSessionHasNoErrors();

        $response = app(SmsService::class)->sendOtp('+2250700000001', '123456');
        $this->assertSame('ProsArtisan : votre code est 123456 (5 min). Ne le donnez à personne.', $response['data']['message']);
    }

    public function test_une_alerte_de_securite_garde_le_push_ou_le_sms(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/messages/jalon.controle_securite.artisan', ['channel_push' => false, 'channel_sms' => false])
            ->assertSessionHasErrors(['channels']);

        $this->actingAs($this->admin())
            ->put('/admin/messages/mission.demande_acceptee.client', ['channel_in_app' => false, 'channel_push' => false, 'channel_sms' => false])
            ->assertSessionHasErrors(['channels']);
    }

    public function test_revenir_au_texte_d_origine_supprime_la_surcharge(): void
    {
        $admin = $this->admin();
        NotificationTemplate::create(['event_key' => 'jalon.paye.artisan', 'push_title' => 'Autre titre']);

        $this->actingAs($admin)->delete('/admin/messages/jalon.paye.artisan')->assertRedirect();

        $this->assertSame(0, NotificationTemplate::count());
        $this->assertSame('Autre titre', AdminActivityLog::where('action', 'notification_template.reset')->sole()->context['avant']['titre']);
    }

    public function test_l_envoi_de_test_part_vers_l_administrateur_seulement(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/messages/jalon.paye.artisan/test', ['channels' => ['push', 'sms']])
            ->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($admin) {
            $payload = json_decode($request->body(), true);

            return $payload['include_aliases']['external_id'] === [(string) $admin->id]
                && $payload['headings']['fr'] === '[Test] Paiement reçu !'
                && $payload['contents']['fr'] === 'Le jalon #2 a été validé. Paiement en cours.';
        });
        // Aucune notification réelle ni ligne de journal pour un test.
        $this->assertSame(0, Notification::count());
        $this->assertSame(0, NotificationDelivery::count());
        $this->assertSame(['push', 'sms'], array_keys(AdminActivityLog::where('action', 'notification_template.test_sent')->sole()->context['resultats']));
    }

    public function test_le_test_de_l_otp_ne_part_jamais_en_push(): void
    {
        $this->actingAs($this->admin())->post('/admin/messages/auth.otp/test', ['channels' => ['push', 'sms']])->assertSessionHas('success');

        Http::assertNothingSent();
        $this->assertSame(['sms'], array_keys(AdminActivityLog::where('action', 'notification_template.test_sent')->sole()->context['resultats']));
    }

    public function test_un_evenement_inconnu_renvoie_404(): void
    {
        $this->actingAs($this->admin())->put('/admin/messages/inconnu.evenement', ['push_title' => 'X'])->assertNotFound();
    }

    public function test_le_journal_filtre_par_statut_et_compte_sur_24_heures(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'name' => 'Koffi Yao']);
        foreach (['envoye', 'envoye', 'echoue'] as $status) {
            NotificationDelivery::create(['user_id' => $artisan->id, 'event_key' => 'jalon.paye.artisan', 'channel' => 'push', 'provider' => 'onesignal', 'status' => $status]);
        }
        NotificationDelivery::create(['user_id' => $artisan->id, 'event_key' => 'jalon.paye.artisan', 'channel' => 'sms', 'provider' => 'log', 'status' => 'envoye']);

        $this->actingAs($this->admin())
            ->get('/admin/messages?delivery_status=echoue')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notificationDeliveries.data', 1)
                ->where('notificationDeliveries.data.0.status_label', 'Échoué')
                ->where('notificationDeliveries.data.0.event_label', 'Étape validée : paiement de l\'artisan')
                ->where('notificationDeliveries.data.0.user.name', 'Koffi Yao')
                ->where('notificationDeliveryStats.sent', 3)
                ->where('notificationDeliveryStats.failed', 1)
                ->where('notificationDeliveryStats.sms_sent', 1));

        $this->actingAs($this->admin())
            ->get('/admin/messages?delivery_search=Koffi&delivery_channel=sms')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notificationDeliveries.data', 1));
    }
}
