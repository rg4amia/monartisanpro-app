<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chantier 14, lot E — préférences de notification par rubrique, accord aux
 * offres et nouveautés, liste paginée et purge des notifications lues.
 */
class Chantier14LotENotificationPreferencesTest extends TestCase
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

    private function user(string $role = 'artisan'): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    private function notifications(): NotificationService
    {
        return app(NotificationService::class);
    }

    /**
     * @return array<string, string>
     */
    private function deliveriesFor(User $user): array
    {
        return NotificationDelivery::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (NotificationDelivery $d) => [$d->channel => $d->status])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function domainsOf(array $payload): array
    {
        return collect($payload['domains'])->keyBy('key')->all();
    }

    // ─── Préférences ───────────────────────────────────────────────────────

    public function test_sans_reglage_tout_est_actif_sauf_les_offres_et_nouveautes(): void
    {
        $artisan = $this->user();

        $response = $this->actingAs($artisan)->getJson('/api/v1/notifications/preferences')->assertOk();

        $this->assertFalse($response->json('data.promotional_push'));
        $domains = $this->domainsOf($response->json('data'));

        $this->assertSame('Missions et devis', $domains['missions']['label']);
        $this->assertTrue($domains['missions']['push']);
        $this->assertTrue($domains['missions']['push_editable']);
        // Aucun message courant ne part par SMS : rien à couper.
        $this->assertFalse($domains['missions']['sms_editable']);
        // Un artisan ne reçoit pas les messages des commandes de matériaux.
        $this->assertArrayNotHasKey('commandes', $domains);
    }

    public function test_une_rubrique_de_messages_essentiels_n_est_pas_reglable(): void
    {
        $artisan = $this->user();

        $domains = $this->domainsOf(
            $this->actingAs($artisan)->getJson('/api/v1/notifications/preferences')->json('data')
        );

        foreach (['securite', 'etapes'] as $domain) {
            $this->assertTrue($domains[$domain]['essential']);
            $this->assertFalse($domains[$domain]['push_editable']);
            $this->assertTrue($domains[$domain]['push']);
        }

        $this->actingAs($artisan)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['securite' => ['push' => false]]])
            ->assertStatus(422)
            ->assertJsonPath('errors.domains.0', NotificationPreferenceService::ESSENTIAL_MESSAGE);

        $this->assertSame(0, NotificationPreference::count());
    }

    public function test_une_rubrique_inconnue_ou_etrangere_au_role_est_refusee(): void
    {
        $artisan = $this->user();

        foreach (['inconnue', 'commandes'] as $domain) {
            $this->actingAs($artisan)
                ->putJson('/api/v1/notifications/preferences', ['domains' => [$domain => ['push' => false]]])
                ->assertStatus(422)
                ->assertJsonPath('errors.domains.0', NotificationPreferenceService::UNKNOWN_DOMAIN_MESSAGE);
        }
    }

    public function test_le_push_coupe_d_une_rubrique_ne_part_plus_mais_la_notification_reste_dans_la_liste(): void
    {
        $artisan = $this->user();

        $this->actingAs($artisan)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['missions' => ['push' => false]]])
            ->assertOk()
            ->assertJsonPath('data.domains.0.key', 'missions')
            ->assertJsonPath('data.domains.0.push', false);

        $this->notifications()->notify($artisan, 'mission.demande_devis.artisan', ['client' => 'Awa']);

        $this->assertSame(1, Notification::where('user_id', $artisan->id)->count());
        $this->assertSame([], $this->deliveriesFor($artisan));
        Http::assertNothingSent();
    }

    public function test_reactiver_une_rubrique_retablit_le_push(): void
    {
        $artisan = $this->user();
        NotificationPreference::create(['user_id' => $artisan->id, 'muted_push_domains' => ['missions']]);

        $this->actingAs($artisan)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['missions' => ['push' => true]]])
            ->assertOk();

        $this->assertNull(NotificationPreference::sole()->muted_push_domains);

        $this->notifications()->notify($artisan, 'mission.demande_devis.artisan', ['client' => 'Awa']);
        $this->assertSame(['push' => 'envoye'], $this->deliveriesFor($artisan));
    }

    public function test_un_message_essentiel_part_meme_si_sa_rubrique_est_coupee(): void
    {
        $livreur = $this->user('livreur');

        $this->actingAs($livreur)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['finances' => ['push' => false]]])
            ->assertOk();

        // Message courant de la rubrique : coupé.
        $this->notifications()->notify($livreur, 'retrait_livreur.refuse', ['reference' => 'RL-1', 'motif' => 'numéro invalide']);
        $this->assertSame([], $this->deliveriesFor($livreur));

        // Paiement reçu : toujours envoyé, push et SMS.
        $this->notifications()->notify($livreur, 'retrait_livreur.verse', ['reference' => 'RL-2', 'montant' => '5 000']);
        $this->assertSame(['push' => 'envoye', 'sms' => 'envoye'], $this->deliveriesFor($livreur));
    }

    public function test_le_sms_d_une_rubrique_se_coupe_quand_un_message_courant_part_par_sms(): void
    {
        $artisan = $this->user();
        // Un administrateur a activé le SMS d'un message courant.
        NotificationTemplate::create(['event_key' => 'mission.demande_devis.artisan', 'channel_sms' => true]);

        $domains = $this->domainsOf(
            $this->actingAs($artisan)->getJson('/api/v1/notifications/preferences')->json('data')
        );
        $this->assertTrue($domains['missions']['sms_editable']);
        $this->assertTrue($domains['missions']['sms']);

        $this->actingAs($artisan)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['missions' => ['sms' => false]]])
            ->assertOk();

        $this->notifications()->notify($artisan, 'mission.demande_devis.artisan', ['client' => 'Awa']);

        $this->assertSame(['push' => 'envoye'], $this->deliveriesFor($artisan));
    }

    public function test_l_accord_aux_offres_et_nouveautes_est_explicite_et_date(): void
    {
        $client = $this->user('client');

        $this->actingAs($client)
            ->putJson('/api/v1/notifications/preferences', ['promotional_push' => true])
            ->assertOk()
            ->assertJsonPath('data.promotional_push', true);

        $preference = NotificationPreference::sole();
        $this->assertTrue($preference->promotional_push);
        $this->assertNotNull($preference->promotional_push_at);

        $this->actingAs($client)
            ->putJson('/api/v1/notifications/preferences', ['promotional_push' => false])
            ->assertOk()
            ->assertJsonPath('data.promotional_push', false);

        $this->assertNull($preference->fresh()->promotional_push_at);
    }

    public function test_regler_une_rubrique_ne_touche_pas_l_accord_promotionnel(): void
    {
        $client = $this->user('client');
        NotificationPreference::create(['user_id' => $client->id, 'promotional_push' => true, 'promotional_push_at' => now()]);

        $this->actingAs($client)
            ->putJson('/api/v1/notifications/preferences', ['domains' => ['missions' => ['push' => false]]])
            ->assertOk()
            ->assertJsonPath('data.promotional_push', true);
    }

    public function test_les_preferences_exigent_une_session(): void
    {
        $this->getJson('/api/v1/notifications/preferences')->assertStatus(401);
        $this->putJson('/api/v1/notifications/preferences', ['promotional_push' => true])->assertStatus(401);
    }

    // ─── Liste ─────────────────────────────────────────────────────────────

    private function seedNotification(User $user, string $event, bool $read = false, array $attributes = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => NotificationCatalog::get($event)['type'],
            'event_key' => $event,
            'title' => 'Titre',
            'body' => 'Texte',
            'data_json' => [],
            'read_at' => $read ? now() : null,
            ...$attributes,
        ]);
    }

    public function test_la_liste_se_charge_page_par_page_et_le_compteur_ne_depend_pas_de_la_page(): void
    {
        $artisan = $this->user();
        foreach (range(1, 5) as $i) {
            $this->seedNotification($artisan, 'mission.demande_devis.artisan');
        }
        $this->seedNotification($artisan, 'jalon.paye.artisan', read: true);
        $this->seedNotification($this->user(), 'mission.demande_devis.artisan');

        $response = $this->actingAs($artisan)->getJson('/api/v1/notifications?per_page=2&page=2')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(6, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertSame(5, $response->json('meta.unread'));
    }

    public function test_la_liste_se_filtre_par_rubrique_et_annonce_les_rubriques_presentes(): void
    {
        $artisan = $this->user();
        $this->seedNotification($artisan, 'mission.demande_devis.artisan');
        $this->seedNotification($artisan, 'mission.cloturee.artisan', read: true);
        $this->seedNotification($artisan, 'jalon.paye.artisan');
        // Notification antérieure au catalogue : visible dans « Tout » seulement.
        Notification::create(['user_id' => $artisan->id, 'type' => 'payment', 'title' => 'Ancienne', 'body' => 'Texte', 'data_json' => []]);

        $response = $this->actingAs($artisan)->getJson('/api/v1/notifications?domain=missions')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame('missions', $response->json('data.0.domain'));
        $this->assertSame(2, $response->json('meta.total'));
        // Compteur et rubriques : indépendants du filtre.
        $this->assertSame(3, $response->json('meta.unread'));
        $this->assertSame([
            ['key' => 'missions', 'label' => 'Missions et devis', 'total' => 2, 'unread' => 1],
            ['key' => 'etapes', 'label' => 'Étapes et paiements de chantier', 'total' => 1, 'unread' => 1],
        ], $response->json('meta.domains'));

        $this->assertCount(4, $this->actingAs($artisan)->getJson('/api/v1/notifications')->json('data'));
    }

    public function test_un_filtre_de_rubrique_inconnu_est_refuse(): void
    {
        $this->actingAs($this->user())
            ->getJson('/api/v1/notifications?domain=finance')
            ->assertStatus(422);
    }

    public function test_marquer_comme_lue_conserve_la_date_de_premiere_lecture(): void
    {
        $artisan = $this->user();
        $notification = $this->seedNotification($artisan, 'mission.demande_devis.artisan', attributes: ['read_at' => now()->subMonths(3)]);
        $firstRead = $notification->read_at->toDateTimeString();

        $this->actingAs($artisan)->putJson("/api/v1/notifications/{$notification->id}/read")->assertOk();
        $this->assertSame($firstRead, $notification->fresh()->read_at->toDateTimeString());

        $this->actingAs($this->user())->putJson("/api/v1/notifications/{$notification->id}/read")->assertStatus(403);
    }

    // ─── Purge ─────────────────────────────────────────────────────────────

    public function test_la_purge_ne_supprime_que_les_notifications_lues_depuis_plus_de_douze_mois(): void
    {
        $artisan = $this->user();
        $oldRead = $this->seedNotification($artisan, 'mission.demande_devis.artisan', attributes: ['read_at' => now()->subMonths(13)]);
        $recentRead = $this->seedNotification($artisan, 'mission.demande_devis.artisan', attributes: ['read_at' => now()->subMonths(11)]);
        $oldUnread = $this->seedNotification($artisan, 'mission.demande_devis.artisan');
        Notification::whereKey($oldUnread->id)->update(['created_at' => now()->subYears(3)]);

        $this->artisan('notifications:purge-read')
            ->expectsOutput('1 notification(s) lue(s) supprimée(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread->id]);
    }
}
