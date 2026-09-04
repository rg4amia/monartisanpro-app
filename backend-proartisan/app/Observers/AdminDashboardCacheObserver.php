<?php

namespace App\Observers;

use App\Services\Admin\AdminDashboardCache;

/**
 * Purge le cache des indicateurs du tableau de bord admin (Chantier C4 / P1-7)
 * dès qu'un modèle qui alimente ces chiffres est créé, modifié ou supprimé.
 *
 * Branché dans AppServiceProvider sur : Transaction, WalletTransaction, Mission,
 * Litige, Jalon, Order — c'est-à-dire exactement les tables agrégées par
 * `AdminService::dashboard()` et `getFinancialKpis()`.
 */
class AdminDashboardCacheObserver
{
    public function __construct(private AdminDashboardCache $cache) {}

    public function saved(mixed $model): void
    {
        $this->cache->flush();
    }

    public function deleted(mixed $model): void
    {
        $this->cache->flush();
    }
}
