<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/v1/missions expose `unreadMessagesCount` par mission afin que le
 * mobile puisse afficher un badge et router une notification de message
 * directement vers la bonne discussion, au lieu d'obliger l'utilisateur à
 * parcourir toutes ses missions pour retrouver celle concernée.
 */
class MissionUnreadMessagesCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeMission(User $client, User $artisan): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réfection de plomberie',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    private function postMessage(Mission $mission, User $sender, ?string $readAt = null): MissionMessage
    {
        return MissionMessage::create([
            'mission_id' => $mission->id,
            'sender_id' => $sender->id,
            'type' => 'text',
            'content' => 'Message de test',
            'read_at' => $readAt,
        ]);
    }

    public function test_unread_count_only_counts_messages_from_the_other_party(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->makeMission($client, $artisan);

        // Deux messages non lus envoyés par l'artisan : comptés pour le client.
        $this->postMessage($mission, $artisan);
        $this->postMessage($mission, $artisan);

        // Message déjà lu : non compté.
        $this->postMessage($mission, $artisan, now()->toDateTimeString());

        // Message envoyé par le client lui-même : jamais compté pour lui.
        $this->postMessage($mission, $client);

        $response = $this->actingAs($client)->getJson('/api/v1/missions');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.0.unreadMessagesCount'));
    }

    public function test_each_party_sees_its_own_unread_count(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->makeMission($client, $artisan);

        $this->postMessage($mission, $artisan);
        $this->postMessage($mission, $client);
        $this->postMessage($mission, $client);
        $this->postMessage($mission, $client);

        $clientView = $this->actingAs($client)->getJson('/api/v1/missions');
        $artisanView = $this->actingAs($artisan)->getJson('/api/v1/missions');

        $this->assertSame(1, $clientView->json('data.0.unreadMessagesCount'));
        $this->assertSame(3, $artisanView->json('data.0.unreadMessagesCount'));
    }

    public function test_unread_count_is_resolved_by_a_single_aggregated_query(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        foreach (range(1, 12) as $ignored) {
            $mission = $this->makeMission($client, $artisan);
            $this->postMessage($mission, $artisan);
            $this->postMessage($mission, $artisan);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($client)->getJson('/api/v1/missions');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        foreach ($response->json('data') as $mission) {
            $this->assertSame(2, $mission['unreadMessagesCount']);
        }

        // Le compteur doit être résolu par une unique sous-requête agrégée,
        // quel que soit le nombre de missions. On cible précisément les
        // requêtes touchant mission_messages plutôt qu'un total absolu :
        // l'endpoint souffre par ailleurs d'un N+1 préexistant sur `devis`,
        // sans rapport avec ce compteur, qui fausserait un seuil global.
        $messageQueries = array_filter(
            $log,
            fn ($q) => str_contains($q['query'], 'mission_messages')
        );

        $this->assertCount(
            1,
            $messageQueries,
            'Le compteur de messages non lus doit tenir en une seule requête agrégée pour 12 missions.'
        );
    }

    public function test_listing_missions_does_not_query_devis_once_per_mission(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        foreach (range(1, 10) as $ignored) {
            $this->makeMission($client, $artisan);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($client)->getJson('/api/v1/missions');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));

        // MissionResource lit trois drapeaux « devis » (devis en attente,
        // devis accepté, présence de devis). Ils étaient auparavant résolus
        // par un `exists` par mission, soit 30 requêtes ici. Ils doivent
        // désormais être agrégés dans la requête de liste.
        $devisQueries = array_filter(
            $log,
            fn ($q) => str_contains($q['query'], 'devis')
        );

        $this->assertLessThanOrEqual(
            1,
            count($devisQueries),
            'Les drapeaux devis doivent être agrégés : '.count($devisQueries).' requêtes `devis` pour 10 missions.'
        );
    }
}
