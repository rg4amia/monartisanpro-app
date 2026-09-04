<?php

namespace Tests\Feature\Admin;

use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C4 (P1-6) — listes « litiges » et « évaluations » paginées + filtres serveur.
 */
class AdminLitigesEvaluationsListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function mission(int $montant = 100000): Mission
    {
        return Mission::create([
            'client_id' => User::factory()->create(['role' => 'client'])->id,
            'artisan_id' => User::factory()->create(['role' => 'artisan'])->id,
            'description' => 'Mission de test',
            'status' => 'in_progress',
            'montant_total' => $montant,
            'montant_materiaux' => (int) ($montant * 0.6),
            'montant_mo' => (int) ($montant * 0.4),
            'ratio_materiaux' => 0.60,
        ]);
    }

    public function test_litiges_are_paginated_with_page_independent_stats(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 25; $i++) {
            Litige::create([
                'mission_id' => $this->mission()->id,
                'declencheur_id' => $admin->id,
                'type' => 'client',
                'description' => 'Litige ordinaire',
                'statut' => 'ouvert',
            ]);
        }
        Litige::create([
            'mission_id' => $this->mission(3_000_000)->id,
            'declencheur_id' => $admin->id,
            'type' => 'client',
            'description' => 'Gros litige',
            'statut' => 'en_cours',
        ]);
        Litige::create([
            'mission_id' => $this->mission()->id,
            'declencheur_id' => $admin->id,
            'type' => 'artisan',
            'description' => 'Litige clos',
            'statut' => 'resolu',
        ]);

        $this->actingAs($admin)->get('/admin/litiges')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/litiges')
            ->has('litigesPage.data', 20)
            ->where('litigesPage.total', 27)
            ->where('litigeStats.open', 26)
            ->where('litigeStats.resolved', 1)
            ->where('litigeStats.high_risk', 1));

        $this->actingAs($admin)->get('/admin/litiges?statut_litige=resolu')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('litigesPage.data', 1));
    }

    public function test_evaluations_and_scores_paginate_with_distinct_page_params(): void
    {
        $admin = $this->admin();
        $artisan = User::factory()->create(['role' => 'artisan', 'name' => 'Koffi Le Menuisier']);

        for ($i = 0; $i < 30; $i++) {
            Evaluation::create([
                'mission_id' => $this->mission()->id,
                'evaluateur_id' => User::factory()->create(['role' => 'client'])->id,
                'evalue_id' => $artisan->id,
                'note' => 4,
                'fiabilite' => 4, 'integrite' => 4, 'qualite' => 4, 'reactivite' => 4,
            ]);
        }

        $this->actingAs($admin)->get('/admin/evaluations')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/evaluations')
            ->has('evaluationsPage.data', 25)
            ->where('evaluationsPage.total', 30)
            ->has('artisansScoresPage.data')
            ->where('evaluationStats.evaluations_total', 30)
            ->where('evaluationStats.note_moyenne', 4));

        $this->actingAs($admin)->get('/admin/evaluations?search_score=Koffi')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('artisansScoresPage.data', 1)
                ->where('artisansScoresPage.data.0.name', 'Koffi Le Menuisier'));
    }
}
