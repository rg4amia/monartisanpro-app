<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use App\Services\AdminService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chantier 14, lot A — pannes de la chaîne de notifications :
 * - les messages de chantier n'étaient jamais notifiés (service injecté en
 *   optionnel, résolu à null, et appelé via une méthode inexistante) ;
 * - le push ne portait pas le `type`, si bien que le toucher n'ouvrait rien.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.rest_api_key' => 'test-rest-key',
        ]);
    }

    private function fakeOneSignal(int $status = 200): void
    {
        Http::fake([
            'onesignal.com/*' => Http::response($status === 200 ? ['id' => 'notif-onesignal'] : ['errors' => ['panne']], $status),
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    /**
     * @return array{0: User, 1: User, 2: Mission}
     */
    private function missionWithParticipants(): array
    {
        $client = $this->user('client');
        $artisan = $this->user('artisan');

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réfection de la salle de bain',
            'status' => 'in_progress',
            'montant_total' => 300000,
            'montant_materiaux' => 195000,
            'montant_mo' => 105000,
            'ratio_materiaux' => 0.65,
            'referent_required' => false,
        ]);

        return [$client, $artisan, $mission];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function oneSignalPayloadsFor(User $user): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn (Request $request) => str_contains($request->url(), 'onesignal.com'))
            ->map(fn (Request $request) => $request->data())
            ->filter(fn (array $payload) => ($payload['include_aliases']['external_id'][0] ?? null) === (string) $user->id)
            ->values()
            ->all();
    }

    public function test_un_message_du_client_notifie_l_artisan(): void
    {
        $this->fakeOneSignal();
        [$client, $artisan, $mission] = $this->missionWithParticipants();

        $this->actingAs($client)
            ->postJson("/api/v1/missions/{$mission->id}/messages", ['content' => 'Bonjour, vous passez demain ?'])
            ->assertCreated();

        $notification = Notification::where('user_id', $artisan->id)->sole();
        $this->assertSame('chat_message', $notification->type);
        $this->assertSame($mission->id, $notification->data_json['mission_id']);
        $this->assertSame(0, Notification::where('user_id', $client->id)->count());

        $payloads = $this->oneSignalPayloadsFor($artisan);
        $this->assertCount(1, $payloads);
        $this->assertSame('chat_message', $payloads[0]['data']['type']);
        $this->assertSame($mission->id, $payloads[0]['data']['mission_id']);
    }

    public function test_un_message_de_l_artisan_notifie_le_client(): void
    {
        $this->fakeOneSignal();
        [$client, $artisan, $mission] = $this->missionWithParticipants();

        $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/messages", ['content' => 'Je passe demain à 9 h.'])
            ->assertCreated();

        $this->assertSame('chat_message', Notification::where('user_id', $client->id)->sole()->type);
        $this->assertCount(1, $this->oneSignalPayloadsFor($client));
    }

    public function test_une_panne_onesignal_ne_bloque_pas_l_envoi_du_message(): void
    {
        $this->fakeOneSignal(500);
        [$client, $artisan, $mission] = $this->missionWithParticipants();

        $this->actingAs($client)
            ->postJson("/api/v1/missions/{$mission->id}/messages", ['content' => 'Message malgré la panne'])
            ->assertCreated();

        // La notification in-app reste enregistrée même si le push échoue.
        $this->assertSame(1, Notification::where('user_id', $artisan->id)->count());
    }

    public function test_le_push_porte_le_type_et_l_identifiant_de_la_notification(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user('artisan');

        app(NotificationService::class)->send(
            $artisan,
            'devis',
            'Devis refusé',
            'Le client a refusé votre devis.',
            ['mission_id' => 42, 'devis_id' => 7]
        );

        $notification = Notification::where('user_id', $artisan->id)->sole();
        $payload = $this->oneSignalPayloadsFor($artisan)[0];

        $this->assertSame('devis', $payload['data']['type']);
        $this->assertSame($notification->id, $payload['data']['notification_id']);
        $this->assertSame(42, $payload['data']['mission_id']);
        $this->assertSame(7, $payload['data']['devis_id']);

        // Les données conservées en base restent celles de l'appelant.
        $this->assertSame(['mission_id' => 42, 'devis_id' => 7], $notification->data_json);
    }

    public function test_la_revue_kyc_n_envoie_qu_un_seul_push(): void
    {
        $this->fakeOneSignal();
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'en_attente']);

        app(AdminService::class)->reviewKyc($admin, $artisan, 'approuve');

        // Le service poussait une seconde notification, au texte différent,
        // en plus de celle de NotificationService::send().
        $payloads = $this->oneSignalPayloadsFor($artisan);
        $this->assertCount(1, $payloads);
        $this->assertSame('kyc', $payloads[0]['data']['type']);
    }

    public function test_le_push_sans_donnees_porte_quand_meme_le_type(): void
    {
        $this->fakeOneSignal();
        $client = $this->user('client');

        app(NotificationService::class)->send($client, 'mission', 'Mission terminée', 'Votre chantier est terminé.');

        $this->assertSame('mission', $this->oneSignalPayloadsFor($client)[0]['data']['type']);
    }
}
