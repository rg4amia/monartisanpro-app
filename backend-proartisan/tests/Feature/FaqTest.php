<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FAQ « Aide et support » de l'app mobile (client/artisan/livreur/fournisseur).
 */
class FaqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /** @param array<int, string> $capabilities */
    private function restrictedAdmin(array $capabilities): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    public function test_public_endpoint_returns_only_active_faqs_for_the_requested_role(): void
    {
        Faq::create(['question' => 'Comment fonctionne le séquestre ?', 'reponse' => 'Réponse client.', 'roles' => ['client'], 'actif' => true, 'ordre' => 1]);
        Faq::create(['question' => 'Comment générer un J-Code ?', 'reponse' => 'Réponse artisan.', 'roles' => ['artisan'], 'actif' => true, 'ordre' => 1]);
        Faq::create(['question' => 'Question masquée', 'reponse' => 'Ne doit pas apparaître.', 'roles' => ['client'], 'actif' => false, 'ordre' => 2]);
        Faq::create(['question' => 'Pour tous', 'reponse' => 'Visible client + artisan.', 'roles' => ['client', 'artisan'], 'actif' => true, 'ordre' => 2]);

        $response = $this->getJson('/api/v1/faqs?role=client')->assertOk();

        $questions = collect($response->json('data'))->pluck('question')->all();

        $this->assertContains('Comment fonctionne le séquestre ?', $questions);
        $this->assertContains('Pour tous', $questions);
        $this->assertNotContains('Comment générer un J-Code ?', $questions);
        $this->assertNotContains('Question masquée', $questions);
    }

    public function test_public_endpoint_rejects_unknown_role(): void
    {
        $this->getJson('/api/v1/faqs?role=admin')->assertStatus(422);
    }

    public function test_faq_admin_page_requires_manage_capability(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get('/admin/faq')
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin(['admin.faq.manage']))
            ->get('/admin/faq')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/faq')->has('faqs'));
    }

    public function test_admin_can_create_update_and_delete_a_faq(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/faq', [
            'question' => 'Comment recevoir mon paiement fournisseur ?',
            'reponse' => 'Virement J+1 garanti après scan du J-Code.',
            'categorie' => 'Paiement',
            'roles' => ['fournisseur'],
            'ordre' => 3,
            'actif' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('faqs', ['question' => 'Comment recevoir mon paiement fournisseur ?']);
        $faq = Faq::where('question', 'Comment recevoir mon paiement fournisseur ?')->firstOrFail();
        $this->assertSame(['fournisseur'], $faq->roles);

        $this->actingAs($admin)->put("/admin/faq/{$faq->id}", [
            'question' => $faq->question,
            'reponse' => $faq->reponse,
            'categorie' => $faq->categorie,
            'roles' => ['fournisseur', 'livreur'],
            'ordre' => 3,
            'actif' => '0',
        ])->assertRedirect();

        $faq->refresh();
        $this->assertSame(['fournisseur', 'livreur'], $faq->roles);
        $this->assertFalse($faq->actif);

        $this->actingAs($admin)->delete("/admin/faq/{$faq->id}")->assertRedirect();
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_admin_cannot_create_faq_with_invalid_role(): void
    {
        $this->actingAs($this->admin())->post('/admin/faq', [
            'question' => 'Question test',
            'reponse' => 'Réponse test',
            'roles' => ['admin'],
        ])->assertSessionHasErrors('faq');

        $this->assertDatabaseMissing('faqs', ['question' => 'Question test']);
    }
}
