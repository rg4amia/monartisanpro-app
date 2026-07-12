<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\JCode;
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

        $totalOrders = Order::where('supplier_id', $supplier->id)->count();
        $pendingOrders = Order::where('supplier_id', $supplier->id)->where('status', 'paid')->count();
        
        $totalRevenue = Order::where('supplier_id', $supplier->id)
            ->where('status', 'delivered')
            ->sum('subtotal');

        $catalogCount = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->count();

        $recentOrders = Order::where('supplier_id', $supplier->id)
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
}
