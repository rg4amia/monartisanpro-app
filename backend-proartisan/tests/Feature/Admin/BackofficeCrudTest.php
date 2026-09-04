<?php

namespace Tests\Feature\Admin;

use App\Models\PromoCode;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier C1 — endpoints CRUD du backoffice repassés en FormRequest + Service.
 */
class BackofficeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_admin_can_create_and_toggle_a_promo_code(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/promo-codes', [
            'code' => 'bienvenue10',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ])->assertRedirect();

        $promo = PromoCode::firstWhere('code', 'BIENVENUE10');
        $this->assertNotNull($promo, 'Le code doit être normalisé en majuscules.');
        $this->assertTrue($promo->is_active);
        $this->assertSame(0, $promo->min_order_amount);

        $this->actingAs($admin)->post("/admin/promo-codes/{$promo->id}/toggle")->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);
    }

    public function test_promo_code_rejects_duplicate_on_create_but_allows_same_code_on_self_update(): void
    {
        $admin = $this->admin();
        $promo = PromoCode::create([
            'code' => 'ETE2026',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/admin/promo-codes', [
            'code' => 'ETE2026',
            'discount_type' => 'fixed',
            'discount_value' => 1000,
        ])->assertSessionHasErrors('code');

        $this->actingAs($admin)->put("/admin/promo-codes/{$promo->id}", [
            'code' => 'ETE2026',
            'discount_type' => 'fixed',
            'discount_value' => 7000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7000, $promo->fresh()->discount_value);
    }

    public function test_admin_can_manage_sectors_and_trades(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/sectors', ['name' => 'Bâtiment'])->assertRedirect();
        $sector = Sector::firstWhere('name', 'Bâtiment');
        $this->assertNotNull($sector);

        $this->actingAs($admin)->post('/admin/sectors', ['name' => 'Bâtiment'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post('/admin/trades', [
            'sector_id' => $sector->id,
            'name' => 'Maçonnerie',
        ])->assertRedirect();
        $this->assertDatabaseHas('trades', ['sector_id' => $sector->id, 'name' => 'Maçonnerie']);

        $this->actingAs($admin)->post('/admin/trades', [
            'sector_id' => $sector->id,
            'name' => 'Maçonnerie',
        ])->assertSessionHasErrors('name');

        $trade = Trade::firstWhere('name', 'Maçonnerie');
        $this->actingAs($admin)->put("/admin/trades/{$trade->id}", ['name' => 'Gros œuvre'])->assertRedirect();
        $this->assertSame('Gros œuvre', $trade->fresh()->name);
    }

    public function test_admin_can_update_ai_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/ai-dashboard/settings', [
            'daily_user_limit' => 25,
            'ai_enabled' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('ai_settings', ['key' => 'daily_user_limit', 'value' => '25']);
        $this->assertDatabaseHas('ai_settings', ['key' => 'ai_enabled', 'value' => '1']);
    }
}
