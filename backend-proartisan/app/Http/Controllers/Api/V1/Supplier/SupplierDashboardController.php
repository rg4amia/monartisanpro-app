<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Http\Controllers\Controller;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\Order;
use App\Models\SupplierProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierDashboardController extends Controller
{
    /**
     * Obtenir les statistiques et le tableau de bord API du fournisseur.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $supplier = $request->user();

        $totalOrders = Order::visibleToSupplier()->where('supplier_id', $supplier->id)->count();
        $pendingOrders = Order::visibleToSupplier()->where('supplier_id', $supplier->id)->where('status', 'paid')->count();

        $totalRevenue = Order::visibleToSupplier()->where('supplier_id', $supplier->id)
            ->where('status', 'delivered')
            ->sum('subtotal');

        $catalogCount = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->count();

        $recentOrders = Order::visibleToSupplier()->where('supplier_id', $supplier->id)
            ->with(['client', 'items.product'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_orders' => $totalOrders,
                    'pending_orders' => $pendingOrders,
                    'total_revenue' => (int) $totalRevenue,
                    'catalog_count' => $catalogCount,
                ],
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    /**
     * Liste des commandes pour le fournisseur connecté.
     */
    public function orders(Request $request): JsonResponse
    {
        $supplier = $request->user();
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = Order::visibleToSupplier()->where('supplier_id', $supplier->id)
            ->with(['client', 'items.product', 'driver']);

        // Sans `per_page`, la liste complète est renvoyée (versions installées).
        if (empty($filters['per_page'])) {
            return response()->json([
                'success' => true,
                'data' => $query->latest()->get(),
            ]);
        }

        // Page par page : les commandes à traiter passent devant les commandes closes.
        $page = $query
            ->orderByRaw("CASE WHEN status IN ('delivered', 'cancelled') THEN 1 ELSE 0 END")
            ->latest()
            ->paginate((int) $filters['per_page']);

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * Liste des litiges pour le fournisseur connecté.
     */
    public function litiges(Request $request): JsonResponse
    {
        $supplier = $request->user();

        // 1. Litiges sur ses commandes directes
        $orderLitiges = Order::visibleToSupplier()->where('supplier_id', $supplier->id)
            ->where('status', 'disputed')
            ->with(['client'])
            ->get();

        // 2. Litiges sur les chantiers/missions où il a fourni des matériaux via JCode
        $missionIds = JCode::where('fournisseur_id', $supplier->id)->pluck('mission_id')->unique();
        // Tout chantier ayant connu un litige, résolu ou non : l'ancien filtre
        // sur l'état « litige », disparu avec la machine à états, ne renvoyait rien.
        $missionLitiges = Mission::whereIn('id', $missionIds)
            ->whereHas('litiges')
            ->with(['client', 'artisan', 'litiges' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'order_litiges' => $orderLitiges,
                'mission_litiges' => $missionLitiges,
            ],
        ]);
    }
}
