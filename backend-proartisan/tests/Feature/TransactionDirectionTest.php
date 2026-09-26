<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sens des montants de l'historique (Chantier 10) : l'acompte versé par un
 * client s'affichait en « + » chez ce même client. Le sens se juge sur les
 * comptes source et destination, pour l'utilisateur qui consulte.
 */
class TransactionDirectionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->makeUser('client');
        $this->artisan = $this->makeUser('artisan');

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Carrelage',
            'status' => 'in_progress',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.6,
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    private function transaction(array $attributes): Transaction
    {
        return Transaction::create(array_merge([
            'provider' => 'wave',
            'statut' => 'confirme',
        ], $attributes));
    }

    private function row(User $viewer, int $transactionId): array
    {
        return collect($this->actingAs($viewer)->getJson('/api/v1/transactions')->assertOk()->json('data'))
            ->firstWhere('id', $transactionId);
    }

    public function test_client_deposit_is_outgoing_for_the_client_and_escrow_for_the_artisan(): void
    {
        $deposit = $this->transaction([
            'mission_id' => $this->mission->id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => 50000,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => 'escrow_mission_'.$this->mission->id,
        ]);

        $forClient = $this->row($this->client, $deposit->id);
        $this->assertSame('sortant', $forClient['direction']);
        $this->assertSame(-50000, $forClient['montantSigne']);
        $this->assertSame('Paiement au coffre de sécurité', $forClient['libelle']);

        $forArtisan = $this->row($this->artisan, $deposit->id);
        $this->assertSame('sequestre', $forArtisan['direction']);
        $this->assertSame(0, $forArtisan['montantSigne']);
        $this->assertSame('Fonds sécurisés par le client', $forArtisan['libelle']);
    }

    public function test_milestone_payment_is_incoming_for_the_artisan(): void
    {
        $release = $this->transaction([
            'mission_id' => $this->mission->id,
            'user_id' => $this->artisan->id,
            'type' => 'liberation_jalon',
            'montant' => 9000,
            'wallet_source' => 'escrow_mission_'.$this->mission->id,
            'wallet_dest' => 'artisan_mobile_money_'.$this->artisan->id,
        ]);

        $row = $this->row($this->artisan, $release->id);
        $this->assertSame('entrant', $row['direction']);
        $this->assertSame(9000, $row['montantSigne']);
        $this->assertSame('Paiement d\'étape de chantier', $row['libelle']);
        $this->assertSame('Confirmé', $row['statutLibelle']);
    }

    public function test_micro_credit_repayment_is_outgoing_despite_a_jalon_account_id(): void
    {
        // `artisan_mo_jalon_{id}` porte un identifiant de jalon : il ne doit
        // jamais être lu comme l'identifiant d'un utilisateur.
        $repayment = $this->transaction([
            'mission_id' => $this->mission->id,
            'user_id' => $this->artisan->id,
            'type' => 'remboursement',
            'montant' => 2000,
            'wallet_source' => 'artisan_mo_jalon_'.$this->client->id,
            'wallet_dest' => 'microfinance_partner',
        ]);

        $row = $this->row($this->artisan, $repayment->id);
        $this->assertSame('sortant', $row['direction']);
        $this->assertSame('Remboursement du micro-crédit', $row['libelle']);
    }

    public function test_driver_withdrawal_and_earning_directions(): void
    {
        $driver = $this->makeUser('livreur');

        $earning = $this->transaction([
            'user_id' => $driver->id,
            'type' => 'liberation_jalon',
            'montant' => 2700,
            'wallet_source' => 'escrow_order_1',
            'wallet_dest' => 'driver_wallet_'.$driver->id,
        ]);
        $withdrawal = $this->transaction([
            'user_id' => $driver->id,
            'type' => 'paiement_livreur',
            'montant' => 2000,
            'statut' => 'echoue',
            'wallet_source' => 'driver_wallet_'.$driver->id,
            'wallet_dest' => 'driver_wave_'.$driver->id,
            'metadata' => ['payout_id' => 1],
        ]);

        $this->assertSame('Gain de course livrée', $this->row($driver, $earning->id)['libelle']);
        $this->assertSame('entrant', $this->row($driver, $earning->id)['direction']);

        $row = $this->row($driver, $withdrawal->id);
        $this->assertSame('sortant', $row['direction']);
        $this->assertSame('Retrait de gains vers Mobile Money', $row['libelle']);
        $this->assertSame('Virement échoué — relance prévue', $row['statutLibelle']);
    }
}
