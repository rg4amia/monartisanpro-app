<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;

/**
 * Cache des indicateurs du tableau de bord admin (Chantier C4 / P1-7).
 *
 * `AdminService::dashboard()` et `AdminService::getFinancialKpis()` agrègent
 * plusieurs dizaines de requêtes (COUNT/SUM + jointures). Le résultat est
 * stabilisé ici pour un TTL court et purgé dès qu'une transaction, une mission,
 * un litige, un jalon ou une commande change (voir AdminDashboardCacheObserver).
 */
class AdminDashboardCache
{
    /** Bump ce suffixe si la forme des données change. */
    private const VERSION = 'v1';

    public const DASHBOARD_KEY = 'admin:dashboard:'.self::VERSION;

    public const FINANCIAL_KPIS_KEY = 'admin:financial_kpis:'.self::VERSION;

    public const TERRITORY_DISTRICTS_KEY = 'admin:territory:districts:'.self::VERSION;

    public const TERRITORY_COMMUNES_KEY = 'admin:territory:communes:'.self::VERSION;

    private const DASHBOARD_TTL = 60;      // secondes

    private const FINANCIAL_KPIS_TTL = 120; // secondes

    private const TERRITORY_TTL = 60;      // secondes

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function dashboard(\Closure $callback)
    {
        return Cache::remember(self::DASHBOARD_KEY, self::DASHBOARD_TTL, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function financialKpis(\Closure $callback)
    {
        return Cache::remember(self::FINANCIAL_KPIS_KEY, self::FINANCIAL_KPIS_TTL, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function territoryDistricts(\Closure $callback)
    {
        return Cache::remember(self::TERRITORY_DISTRICTS_KEY, self::TERRITORY_TTL, $callback);
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function territoryCommunes(\Closure $callback)
    {
        return Cache::remember(self::TERRITORY_COMMUNES_KEY, self::TERRITORY_TTL, $callback);
    }

    public function flush(): void
    {
        Cache::forget(self::DASHBOARD_KEY);
        Cache::forget(self::FINANCIAL_KPIS_KEY);
        Cache::forget(self::TERRITORY_DISTRICTS_KEY);
        Cache::forget(self::TERRITORY_COMMUNES_KEY);
    }
}
