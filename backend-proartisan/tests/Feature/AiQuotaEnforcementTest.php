<?php

namespace Tests\Feature;

use App\Models\AiUserQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Enforcement du quota IA sur les routes mobiles authentifiées
 * (`/api/v1/chat`, `/api/v1/search`). Les limiteurs `throttle:*` sont
 * désactivés en env `testing` : c'est `AiMonitoringService::checkUserLimit()`
 * qui est éprouvé ici.
 */
class AiQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function logUsage(User $user, int $count, string $action = 'chat'): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('ai_usage_logs')->insert([
                'user_id' => $user->id,
                'model_name' => 'gemini-3.6-flash',
                'action_type' => $action,
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
                'response_time_ms' => 100,
                'status_code' => 200,
                'estimated_cost_usd' => 0.001,
                'created_at' => now()->subMinutes($i + 1),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_chat_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat', ['message' => 'test'])->assertUnauthorized();
    }

    public function test_chat_is_blocked_once_the_daily_limit_is_reached(): void
    {
        $user = User::factory()->create(['role' => 'artisan']);
        DB::table('ai_settings')->where('key', 'daily_user_limit')->update(['value' => '3']);
        $this->logUsage($user, 3);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat', ['message' => 'Dosage béton ?'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_per_user_override_takes_precedence_over_the_global_limit(): void
    {
        $user = User::factory()->create(['role' => 'artisan']);
        DB::table('ai_settings')->where('key', 'daily_user_limit')->update(['value' => '2']);
        AiUserQuota::create(['user_id' => $user->id, 'daily_limit' => 10]);
        $this->logUsage($user, 5);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat', ['message' => 'Question BTP'])
            ->assertOk()
            ->assertJsonStructure(['response', 'quota' => ['daily_used', 'daily_limit', 'blocked']]);
    }

    public function test_a_blocked_user_is_refused_immediately(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        AiUserQuota::create(['user_id' => $user->id, 'blocked' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat', ['message' => 'test'])
            ->assertStatus(429);
    }

    public function test_search_endpoint_shares_the_same_quota_gate(): void
    {
        $user = User::factory()->create(['role' => 'artisan']);
        DB::table('ai_settings')->where('key', 'daily_user_limit')->update(['value' => '1']);
        $this->logUsage($user, 1, 'search');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/search', ['tags' => ['fissure_structure']])
            ->assertStatus(429);
    }

    public function test_chat_never_leaks_the_raw_gemini_error_body_to_the_user(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.qdrant.url' => null]);
        Http::fake([
            '*' => Http::response([
                'error' => ['code' => 429, 'message' => 'You exceeded your current quota, please check your plan and billing details.'],
            ], 429),
        ]);

        $user = User::factory()->create(['role' => 'artisan']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/chat', ['message' => 'Comment devenir propriétaire ?'])
            ->assertOk();

        $reply = $response->json('response');
        $this->assertStringNotContainsStringIgnoringCase('exceeded your current quota', $reply);
        $this->assertStringNotContainsStringIgnoringCase('Erreur API Gemini', $reply);
        $this->assertStringNotContainsStringIgnoringCase('billing', $reply);
        $this->assertStringContainsString('assistant IA', $reply);
    }
}
