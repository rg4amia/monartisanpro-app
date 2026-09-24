<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankTransferSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Coordonnées de virement bancaire saisies dans le backoffice : plus aucune
 * valeur écrite dans le code, virement indisponible tant qu'elles manquent
 * (jamais de compte inventé, Règle d'or 29), IBAN validé et modification
 * auditée.
 */
class BankTransferSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_IBAN = 'CI93 CI00 8011 1301 1342 9120 0589';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function offerFor(User $client): RecruitmentOffer
    {
        $trade = Trade::create(['sector_id' => Sector::create(['name' => 'BTP'])->id, 'name' => 'Maçonnerie']);

        return RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Renfort maçons',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Cocody',
            'status' => 'active',
        ]);
    }

    private function payByBankTransfer(User $client, RecruitmentOffer $offer): TestResponse
    {
        return $this->actingAs($client)->postJson("/api/v1/recruitment-offers/{$offer->id}/unlock-applicants", [
            'daily_rate' => 10000,
            'total_days' => 3,
            'provider' => 'virement_bancaire',
        ]);
    }

    public function test_bank_transfer_is_refused_until_details_are_configured(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->payByBankTransfer($client, $this->offerFor($client))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Transaction::count(), 'Aucune transaction ne doit être créée sans coordonnées bancaires.');
    }

    public function test_admin_configures_details_then_payers_receive_them(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->put('/admin/settings/bank-transfer', [
                'bank_name' => 'Banque Atlantique CI',
                'account_name' => 'PROSARTISAN SEQUESTRE',
                'iban' => strtolower(self::VALID_IBAN),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $response = $this->payByBankTransfer($client, $this->offerFor($client))->assertOk();

        $this->assertSame(self::VALID_IBAN, $response->json('data.virement_instructions.iban'));
        $this->assertSame('Banque Atlantique CI', $response->json('data.virement_instructions.bank_name'));
        $this->assertSame('PROSARTISAN SEQUESTRE', $response->json('data.virement_instructions.account_name'));

        $audit = AdminActivityLog::where('action', 'settings.bank_transfer.updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->admin_id);
        $this->assertSame(self::VALID_IBAN, $audit->context['after']['iban']);
    }

    public function test_invalid_iban_is_rejected(): void
    {
        $admin = $this->admin();

        // Clé de contrôle fausse (93 → 94).
        $this->actingAs($admin)
            ->put('/admin/settings/bank-transfer', [
                'bank_name' => 'Banque',
                'account_name' => 'Titulaire',
                'iban' => 'CI94 CI00 8011 1301 1342 9120 0589',
            ])
            ->assertSessionHasErrors('iban');

        $this->actingAs($admin)
            ->put('/admin/settings/bank-transfer', ['bank_name' => '', 'account_name' => '', 'iban' => 'abc'])
            ->assertSessionHasErrors(['bank_name', 'account_name', 'iban']);

        $this->assertNull(app(BankTransferSettingsService::class)->details());
    }

    public function test_generic_settings_editor_cannot_bypass_iban_validation(): void
    {
        app(BankTransferSettingsService::class)->update([
            'bank_name' => 'Banque',
            'account_name' => 'Titulaire',
            'iban' => self::VALID_IBAN,
        ]);
        $ibanSetting = Setting::where('key', 'virement_iban')->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/admin/settings/{$ibanSetting->id}", ['value' => 'FAUX'])
            ->assertSessionHasErrors('value');

        $this->assertSame(self::VALID_IBAN, $ibanSetting->fresh()->value);
    }

    public function test_non_admin_cannot_change_bank_details(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($client)
            ->put('/admin/settings/bank-transfer', [
                'bank_name' => 'Banque pirate',
                'account_name' => 'Pirate',
                'iban' => self::VALID_IBAN,
            ]);

        $this->assertNull(app(BankTransferSettingsService::class)->details());
    }
}
