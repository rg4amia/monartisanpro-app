<?php

namespace Tests\Feature\Admin;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C4 (P1-6) — journal financier des transactions paginé + filtres serveur.
 */
class AdminTransactionsListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function tx(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'type' => 'acompte',
            'montant' => 10000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'REF-'.fake()->unique()->numerify('######'),
        ], $overrides));
    }

    public function test_transactions_are_paginated_and_stats_are_page_independent(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 60; $i++) {
            $this->tx(['statut' => 'confirme', 'type' => 'acompte', 'montant' => 1000]);
        }
        $this->tx(['statut' => 'en_attente']);
        $this->tx(['statut' => 'echoue']);

        $this->actingAs($admin)->get('/admin/transactions')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/transactions')
            ->has('transactionsPage.data', 50)
            ->where('transactionsPage.total', 62)
            ->where('transactionStats.pending', 1)
            ->where('transactionStats.failed', 1)
            ->where('transactionStats.confirmed', 60)
            ->where('transactionStats.escrow', 60000));
    }

    public function test_status_and_type_filters_apply_server_side(): void
    {
        $admin = $this->admin();
        $this->tx(['statut' => 'confirme', 'type' => 'acompte']);
        $this->tx(['statut' => 'echoue', 'type' => 'acompte']);
        $this->tx(['statut' => 'confirme', 'type' => 'liberation_jalon']);

        $this->actingAs($admin)->get('/admin/transactions?status_tx=echoue')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('transactionsPage.data', 1));

        $this->actingAs($admin)->get('/admin/transactions?type_tx=liberation_jalon')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('transactionsPage.data', 1));
    }

    public function test_search_matches_reference(): void
    {
        $admin = $this->admin();
        $this->tx(['reference_externe' => 'WAVE-SPECIAL-001']);
        $this->tx(['reference_externe' => 'OM-OTHER-002']);

        $this->actingAs($admin)->get('/admin/transactions?search_tx=SPECIAL')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactionsPage.data', 1)
                ->where('transactionsPage.data.0.reference_externe', 'WAVE-SPECIAL-001'));
    }
}
