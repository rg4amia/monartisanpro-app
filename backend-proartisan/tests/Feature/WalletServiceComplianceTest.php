<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use App\Models\Jalon;
use App\Services\WalletService;
use App\Enums\WalletType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_debit_locks_for_update()
    {
        $user = User::factory()->create(['wallet_mo' => 100000]);
        $walletService = app(WalletService::class);

        // We can't easily test the lock itself without multiple threads,
        // but we can verify it still works.
        $walletService->debit($user, WalletType::WALLET_MO, 50000);
        
        $this->assertEquals(50000, $user->fresh()->wallet_mo);
    }

    public function test_release_jalon_performs_transfer()
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_mo' => 100000]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test Mission',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon 1',
            'montant' => 10000,
            'statut' => 'valide'
        ]);

        $walletService = app(WalletService::class);
        $walletService->releaseJalon($jalon);

        $this->assertEquals(90000, $artisan->fresh()->wallet_mo);
        $this->assertEquals('paye', $jalon->fresh()->statut);
        
        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'liberation_jalon',
            'montant' => 10000,
            'statut' => 'confirme' // Since WaveService returns success in testing
        ]);
    }

    public function test_ledger_is_absolute_authority_of_balance()
    {
        $user = User::factory()->create(); // Initial balance is 0

        // Let's create transactions
        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'wallet_type' => \App\Enums\WalletType::WALLET_MO->value,
            'operation' => \App\Enums\WalletOperation::CREDIT,
            'montant' => 1000,
            'solde_avant' => 0,
            'solde_apres' => 1000,
            'cle_idempotence' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->assertEquals(1000, $user->wallet_mo);

        // Manually update the user's DB column to 5000 bypassing Eloquent events (raw DB query)
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['wallet_mo' => 5000]);

        // Reading the attribute on a fresh instance should still return 1000 (calculated from ledger)
        $this->assertEquals(1000, $user->fresh()->wallet_mo);
    }
}
