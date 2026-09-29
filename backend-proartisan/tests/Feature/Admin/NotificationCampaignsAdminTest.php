<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Commune;
use App\Models\Communication;
use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 14, lot D — campagnes push et SMS du backoffice.
 */
class NotificationCampaignsAdminTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maintenance du 5 octobre',
            'nature' => 'service',
            'push_title' => 'Maintenance programmée',
            'push_body' => 'L\'application sera indisponible dimanche de 2 h à 4 h.',
            'sms_body' => 'ProsArtisan : maintenance dimanche de 2 h a 4 h.',
            'channel_in_app' => true,
            'channel_push' => true,
            'channel_sms' => true,
            'target' => ['roles' => ['artisan'], 'commune_ids' => [], 'kyc_statuses' => [], 'user_ids' => []],
            'open_screen' => 'notifications',
            'communication_id' => null,
        ], $overrides);
    }

    private function campaign(array $overrides = []): NotificationCampaign
    {
        $payload = $this->payload();

        return NotificationCampaign::create(array_merge([
            'name' => $payload['name'],
            'nature' => 'service',
            'push_title' => $payload['push_title'],
            'push_body' => $payload['push_body'],
            'sms_body' => $payload['sms_body'],
            'channel_in_app' => true,
            'channel_push' => true,
            'channel_sms' => true,
            'target_json' => ['roles' => ['artisan']],
            'open_screen' => 'notifications',
            'status' => NotificationCampaign::STATUS_DRAFT,
        ], $overrides));
    }

    private function makeArtisan(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'artisan', 'kyc_status' => 'actif'], $attributes));
    }

    public function test_l_onglet_liste_les_campagnes_et_les_options_de_ciblage(): void
    {
        $this->makeArtisan();
        $this->campaign();

        $this->actingAs($this->admin())
            ->get('/admin/campagnes-notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/notification-campaigns')
                ->has('notificationCampaigns.data', 1)
                ->where('notificationCampaigns.data.0.status_label', 'Brouillon')
                ->where('notificationCampaigns.data.0.estimate.recipients', 1)
                ->where('notificationCampaigns.data.0.estimate.sms_messages', 1)
                ->missing('notificationCampaigns.data.0.estimate.cost')
                ->has('notificationCampaignOptions.roles')
                ->where('notificationCampaignOptions.max_recipients', 20000));
    }

    public function test_la_capacite_broadcast_est_distincte_de_la_gestion_des_messages(): void
    {
        $this->assertTrue(Permission::where('name', 'admin.notifications.broadcast')->exists());

        $messagesOnly = $this->restrictedAdmin(['admin.notifications.manage']);
        $this->actingAs($messagesOnly)->get('/admin/campagnes-notifications')->assertForbidden();
        $this->actingAs($messagesOnly)->post('/admin/campagnes-notifications', $this->payload())->assertForbidden();
        $this->assertSame(0, NotificationCampaign::count());

        $this->actingAs($this->restrictedAdmin(['admin.notifications.broadcast']))->get('/admin/campagnes-notifications')->assertOk();
    }

    public function test_une_campagne_est_creee_en_brouillon_et_auditee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $campaign = NotificationCampaign::sole();
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertSame(['artisan'], $campaign->target_json['roles']);
        $this->assertSame($admin->id, $campaign->created_by);
        $this->assertSame('Maintenance du 5 octobre', AdminActivityLog::where('action', 'notification_campaign.created')->sole()->subject_label);
        // Rien ne part tant que la campagne n'est pas programmée.
        $this->assertSame(0, Notification::count());
    }

    public function test_une_campagne_promotionnelle_ne_peut_pas_partir_par_sms(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/campagnes-notifications', $this->payload(['nature' => 'promotionnel']))
            ->assertSessionHasErrors(['channel_sms']);

        $this->assertSame(0, NotificationCampaign::count());
    }

    public function test_les_textes_et_le_ciblage_sont_controles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['push_body' => 'Bonjour {nom}, découvrez nos offres.']))
            ->assertSessionHasErrors(['push_body']);
        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['target' => ['roles' => []]]))
            ->assertSessionHasErrors(['target']);
        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['target' => ['roles' => ['admin']]]))
            ->assertSessionHasErrors(['target.roles.0']);
        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['channel_in_app' => false, 'channel_push' => false, 'channel_sms' => false]))
            ->assertSessionHasErrors(['channels']);
        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['sms_body' => str_repeat('Message très long ', 30)]))
            ->assertSessionHasErrors(['sms_body']);

        $draft = Communication::create(['type' => 'annonce', 'titre' => 'Brouillon', 'contenu' => 'x', 'cibles_json' => ['artisan'], 'statut' => 'brouillon', 'auteur_id' => $admin->id]);
        $this->actingAs($admin)->post('/admin/campagnes-notifications', $this->payload(['open_screen' => 'communication', 'communication_id' => $draft->id]))
            ->assertSessionHasErrors(['communication_id']);

        $this->assertSame(0, NotificationCampaign::count());
    }

    public function test_une_campagne_partie_n_est_ni_modifiable_ni_supprimable(): void
    {
        $admin = $this->admin();
        $sent = $this->campaign(['status' => NotificationCampaign::STATUS_SENT, 'served_count' => 3]);

        $this->actingAs($admin)->put("/admin/campagnes-notifications/{$sent->id}", $this->payload(['name' => 'Autre']))
            ->assertSessionHasErrors(['campaign']);
        $this->actingAs($admin)->delete("/admin/campagnes-notifications/{$sent->id}")
            ->assertSessionHasErrors(['campaign']);

        $this->assertSame('Maintenance du 5 octobre', $sent->fresh()->name);

        $draft = $this->campaign();
        $this->actingAs($admin)->put("/admin/campagnes-notifications/{$draft->id}", $this->payload(['name' => 'Maintenance reportée']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Maintenance reportée', $draft->fresh()->name);

        $this->actingAs($admin)->delete("/admin/campagnes-notifications/{$draft->id}")->assertSessionHasNoErrors();
        $this->assertNull(NotificationCampaign::find($draft->id));
        $this->assertTrue(AdminActivityLog::where('action', 'notification_campaign.deleted')->exists());
    }

    public function test_dupliquer_cree_un_brouillon(): void
    {
        $sent = $this->campaign(['status' => NotificationCampaign::STATUS_SENT]);

        $this->actingAs($this->admin())->post("/admin/campagnes-notifications/{$sent->id}/dupliquer")->assertSessionHasNoErrors();

        $copy = NotificationCampaign::where('id', '!=', $sent->id)->sole();
        $this->assertSame('Copie de Maintenance du 5 octobre', $copy->name);
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $copy->status);
        $this->assertSame(['roles' => ['artisan']], $copy->target_json);
    }

    public function test_le_ciblage_exclut_admins_et_comptes_suspendus_anonymises_ou_supprimes(): void
    {
        $commune = Commune::create(['name' => 'Cocody', 'slug' => 'cocody', 'city' => 'Abidjan', 'country_code' => 'CI']);
        $kept = $this->makeArtisan(['commune_id' => $commune->id]);
        $this->makeArtisan(['commune_id' => $commune->id, 'account_status' => 'suspendu']);
        $this->makeArtisan(['commune_id' => $commune->id, 'anonymized_at' => now()]);
        $this->makeArtisan(['commune_id' => $commune->id])->delete();
        $this->makeArtisan(['commune_id' => $commune->id, 'kyc_status' => 'en_attente']);
        $this->makeArtisan(); // autre commune
        User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif', 'commune_id' => $commune->id]);

        $campaign = $this->campaign(['target_json' => ['roles' => ['artisan', 'client'], 'commune_ids' => [$commune->id], 'kyc_statuses' => ['actif']]]);

        $this->actingAs($this->admin())->post("/admin/campagnes-notifications/{$campaign->id}/programmer")->assertSessionHasNoErrors();
        $this->assertSame(1, $campaign->fresh()->recipients_count);

        $this->artisan('notifications:send-campaigns')->assertSuccessful();

        $this->assertSame([$kept->id], NotificationCampaignRecipient::pluck('user_id')->all());
    }

    public function test_la_campagne_part_par_lots_sans_jamais_relancer_un_destinataire(): void
    {
        config(['prosartisan.notifications.campaign_batch_size' => 2]);
        $artisans = collect(range(1, 3))->map(fn () => $this->makeArtisan());
        $withoutPhone = $this->makeArtisan(['phone' => '']);
        $campaign = $this->campaign(['status' => NotificationCampaign::STATUS_SCHEDULED, 'scheduled_at' => now()->subMinute(), 'recipients_count' => 4]);

        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        $campaign->refresh();
        $this->assertSame(NotificationCampaign::STATUS_SENDING, $campaign->status);
        $this->assertSame(2, $campaign->served_count);

        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        $campaign->refresh();

        $this->assertSame(NotificationCampaign::STATUS_SENT, $campaign->status);
        $this->assertSame(4, $campaign->served_count);
        $this->assertSame(4, NotificationCampaignRecipient::where('campaign_id', $campaign->id)->count());

        // In-app : une notification par destinataire, routée vers l'écran choisi.
        $notification = Notification::where('user_id', $artisans->first()->id)->sole();
        $this->assertSame('campaign', $notification->type);
        $this->assertSame('Maintenance programmée', $notification->title);
        $this->assertSame(['type' => 'campaign', 'event' => 'campagne', 'campaign_id' => $campaign->id, 'screen' => 'notifications'], $notification->data_json);
        $this->assertSame(4, Notification::where('type', 'campaign')->count());

        // Push groupé : un appel OneSignal par lot, jamais un par destinataire.
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) use ($artisans) {
            $payload = json_decode($request->body(), true);

            return $payload['include_aliases']['external_id'] === [(string) $artisans[0]->id, (string) $artisans[1]->id]
                && $payload['data']['type'] === 'campaign';
        });

        // Journal : une ligne par destinataire et par canal, rattachée à la campagne.
        $this->assertSame(4, NotificationDelivery::where('campaign_id', $campaign->id)->where('channel', 'push')->count());
        $this->assertSame(3, NotificationDelivery::where('campaign_id', $campaign->id)->where('channel', 'sms')->where('status', 'envoye')->count());
        $this->assertSame('ignore', NotificationDelivery::where('user_id', $withoutPhone->id)->where('channel', 'sms')->sole()->status);
        $this->assertSame(7, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame(4, AdminActivityLog::where('action', 'notification_campaign.sent')->sole()->context['destinataires']);

        // Un passage de plus ne renvoie rien.
        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame(4, Notification::where('type', 'campaign')->count());
    }

    public function test_une_campagne_programmee_plus_tard_attend_sa_date(): void
    {
        $this->makeArtisan();
        $campaign = $this->campaign();

        $this->actingAs($this->admin())
            ->post("/admin/campagnes-notifications/{$campaign->id}/programmer", ['scheduled_at' => now()->addDay()->toDateTimeString()])
            ->assertSessionHasNoErrors();

        $this->artisan('notifications:send-campaigns')->assertSuccessful();

        $this->assertSame(NotificationCampaign::STATUS_SCHEDULED, $campaign->fresh()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_une_campagne_annulee_s_arrete(): void
    {
        config(['prosartisan.notifications.campaign_batch_size' => 1]);
        $this->makeArtisan();
        $this->makeArtisan();
        $campaign = $this->campaign(['status' => NotificationCampaign::STATUS_SCHEDULED, 'scheduled_at' => now()->subMinute()]);

        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        $this->actingAs($this->admin())->post("/admin/campagnes-notifications/{$campaign->id}/annuler")->assertSessionHasNoErrors();
        $this->artisan('notifications:send-campaigns')->assertSuccessful();

        $campaign->refresh();
        $this->assertSame(NotificationCampaign::STATUS_CANCELLED, $campaign->status);
        $this->assertSame(1, $campaign->served_count);
        $this->assertSame(1, Notification::count());
        $this->assertSame(1, AdminActivityLog::where('action', 'notification_campaign.cancelled')->sole()->context['destinataires_servis']);
    }

    public function test_une_campagne_promotionnelle_ne_vise_que_les_comptes_consentants(): void
    {
        $consenting = $this->makeArtisan();
        NotificationPreference::create(['user_id' => $consenting->id, 'promotional_push' => true]);
        $refusing = $this->makeArtisan();
        NotificationPreference::create(['user_id' => $refusing->id, 'promotional_push' => false]);
        $this->makeArtisan(); // jamais consulté : désactivé par défaut

        $campaign = $this->campaign(['nature' => 'promotionnel', 'channel_sms' => false, 'sms_body' => null]);
        $this->actingAs($this->admin())->post("/admin/campagnes-notifications/{$campaign->id}/programmer")->assertSessionHasNoErrors();
        $this->assertSame(1, $campaign->fresh()->recipients_count);

        $this->artisan('notifications:send-campaigns')->assertSuccessful();
        $this->assertSame([$consenting->id], NotificationCampaignRecipient::pluck('user_id')->all());
        $this->assertSame(0, NotificationDelivery::where('channel', 'sms')->count());
    }

    public function test_la_programmation_refuse_un_ciblage_vide_ou_au_dela_du_plafond(): void
    {
        $admin = $this->admin();
        $promo = $this->campaign(['nature' => 'promotionnel', 'channel_sms' => false, 'sms_body' => null]);
        $this->makeArtisan();

        $this->actingAs($admin)->post("/admin/campagnes-notifications/{$promo->id}/programmer")->assertSessionHasErrors(['target']);
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $promo->fresh()->status);

        config(['prosartisan.notifications.campaign_max_recipients' => 1]);
        $this->makeArtisan();
        $campaign = $this->campaign();
        $this->actingAs($admin)->post("/admin/campagnes-notifications/{$campaign->id}/programmer")->assertSessionHasErrors(['target']);
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
    }

    public function test_l_envoi_de_test_part_vers_l_administrateur_seulement(): void
    {
        $admin = $this->admin();
        $this->makeArtisan();
        $campaign = $this->campaign();

        $this->actingAs($admin)
            ->post("/admin/campagnes-notifications/{$campaign->id}/test", ['channels' => ['push', 'sms']])
            ->assertSessionHas('success');

        Http::assertSent(function (Request $request) use ($admin) {
            $payload = json_decode($request->body(), true);

            return $payload['include_aliases']['external_id'] === [(string) $admin->id]
                && $payload['headings']['fr'] === '[Test] Maintenance programmée';
        });
        $this->assertSame(0, Notification::count());
        $this->assertSame(0, NotificationDelivery::count());
        $this->assertSame(0, NotificationCampaignRecipient::count());
        $this->assertSame(NotificationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertSame(['push', 'sms'], array_keys(AdminActivityLog::where('action', 'notification_campaign.test_sent')->sole()->context['resultats']));
    }

    public function test_la_recherche_d_utilisateurs_ignore_les_administrateurs(): void
    {
        $this->makeArtisan(['name' => 'Kouadio Artisan']);
        User::factory()->create(['role' => 'admin', 'name' => 'Kouadio Admin']);

        $this->actingAs($this->admin())
            ->getJson('/admin/campagnes-notifications/utilisateurs?q=Kouadio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kouadio Artisan');
    }

    public function test_le_journal_des_envois_nomme_la_campagne(): void
    {
        $artisan = $this->makeArtisan();
        $campaign = $this->campaign(['name' => 'Rentrée 2026']);
        NotificationDelivery::create(['campaign_id' => $campaign->id, 'user_id' => $artisan->id, 'event_key' => 'campagne', 'channel' => 'push', 'provider' => 'onesignal', 'status' => 'envoye']);

        $this->actingAs($this->admin())
            ->get('/admin/messages?delivery_search=Rentrée')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notificationDeliveries.data', 1)
                ->where('notificationDeliveries.data.0.event_label', 'Campagne : Rentrée 2026'));
    }
}
