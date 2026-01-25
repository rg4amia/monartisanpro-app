<?php

namespace Tests\Feature\Api\V1\Marketplace;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test client user
        DB::table('users')->insert([
            'id' => 'client-test-id',
            'email' => 'client@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'CLIENT',
            'account_status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_client_can_create_mission(): void
    {
        $missionData = [
            'description' => 'Réparation urgente de plomberie dans la cuisine',
            'category' => TradeCategory::PLUMBER,
            'latitude' => 5.3600,
            'longitude' => -4.0083,
            'budget_min_centimes' => 5000000, // 50,000 XOF
            'budget_max_centimes' => 10000000, // 100,000 XOF
        ];

        $response = $this->actingAs((object) ['id' => 'client-test-id', 'user_type' => 'CLIENT'])
            ->postJson('/api/v1/missions', $missionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'client_id',
                    'description',
                    'category' => ['value', 'label'],
                    'location' => ['latitude', 'longitude'],
                    'budget' => [
                        'min' => ['centimes', 'francs', 'formatted'],
                        'max' => ['centimes', 'francs', 'formatted'],
                    ],
                    'status' => ['value', 'label'],
                    'quotes_count',
                    'can_receive_more_quotes',
                    'created_at',
                ],
            ]);

        // Verify mission was saved to database
        $this->assertDatabaseHas('missions', [
            'client_id' => 'client-test-id',
            'description' => 'Réparation urgente de plomberie dans la cuisine',
            'trade_category' => TradeCategory::PLUMBER,
            'status' => 'OPEN',
        ]);
    }

    public function test_mission_creation_requires_authentication(): void
    {
        $missionData = [
            'description' => 'Test mission',
            'category' => TradeCategory::PLUMBER,
            'latitude' => 5.3600,
            'longitude' => -4.0083,
            'budget_min_centimes' => 5000000,
            'budget_max_centimes' => 10000000,
        ];

        $response = $this->postJson('/api/v1/missions', $missionData);

        $response->assertStatus(401);
    }

    public function test_mission_creation_validates_required_fields(): void
    {
        $response = $this->actingAs((object) ['id' => 'client-test-id', 'user_type' => 'CLIENT'])
            ->postJson('/api/v1/missions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'description',
                'category',
                'latitude',
                'longitude',
                'budget_min_centimes',
                'budget_max_centimes',
            ]);
    }

    public function test_client_can_list_their_missions(): void
    {
        // Create a mission in the database
        DB::table('missions')->insert([
            'id' => 'mission-test-id',
            'client_id' => 'client-test-id',
            'description' => 'Test mission',
            'trade_category' => TradeCategory::PLUMBER,
            'location' => json_encode(['latitude' => 5.3600, 'longitude' => -4.0083]),
            'budget_min_centimes' => 5000000,
            'budget_max_centimes' => 10000000,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs((object) ['id' => 'client-test-id', 'user_type' => 'CLIENT'])
            ->getJson('/api/v1/missions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'client_id',
                        'description',
                        'category',
                        'location',
                        'budget',
                        'status',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);
    }
}
