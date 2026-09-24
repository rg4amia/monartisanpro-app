<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\SupplierCashout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash-out quincaillerie depuis le backoffice : chaque étape (création,
 * approbation, paiement, rejet) aboutit et laisse une trace d'audit.
 *
 * Le journal d'audit était injecté en dépendance optionnelle (`?AdminActivityLogger
 * $audit = null`), que le conteneur résolvait à `null` : aucune opération de
 * cash-out n'était journalisée (Règle d'or 17), et les appels, écrits avec
 * l'administrateur à la place du nom de l'action, ne s'exécutaient jamais.
 */
class AdminCashoutAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function storeCashout(User $admin): SupplierCashout
    {
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->post('/admin/cashouts', [
                'supplier_id' => $supplier->id,
                'beneficiary_name' => 'Kouassi Yao',
                'beneficiary_phone' => '+2250700000001',
                'montant_brut' => 50000,
                'mode_retrait' => 'wave',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return SupplierCashout::where('supplier_id', $supplier->id)->firstOrFail();
    }

    public function test_store_approve_and_complete_are_audited(): void
    {
        $admin = $this->admin();
        $cashout = $this->storeCashout($admin);

        $this->actingAs($admin)->post("/admin/cashouts/{$cashout->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/cashouts/{$cashout->id}/complete")->assertRedirect();

        $this->assertSame(
            ['cashout.approved', 'cashout.completed', 'cashout.created'],
            AdminActivityLog::where('subject_id', $cashout->id)
                ->where('subject_type', SupplierCashout::class)
                ->orderBy('action')
                ->pluck('action')
                ->all(),
        );
        $this->assertSame($admin->id, AdminActivityLog::where('action', 'cashout.created')->value('admin_id'));
    }

    public function test_reject_is_audited(): void
    {
        $admin = $this->admin();
        $cashout = $this->storeCashout($admin);

        $this->actingAs($admin)
            ->post("/admin/cashouts/{$cashout->id}/reject", ['reason' => 'Pièce d\'identité illisible'])
            ->assertRedirect();

        $this->assertTrue(AdminActivityLog::where('action', 'cashout.rejected')->where('subject_id', $cashout->id)->exists());
    }
}
