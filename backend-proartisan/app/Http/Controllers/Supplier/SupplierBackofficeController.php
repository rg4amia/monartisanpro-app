<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Mission;
use App\Models\JCode;
use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierBackofficeController extends Controller
{
    /**
     * Dashboard du fournisseur.
     */
    public function dashboard(Request $request): Response
    {
        $supplier = $request->user();

        // Statistiques
        $totalOrders = Order::where('supplier_id', $supplier->id)->count();
        $pendingOrders = Order::where('supplier_id', $supplier->id)->where('status', 'paid')->count();
        
        $totalRevenue = Order::where('supplier_id', $supplier->id)
            ->where('status', 'delivered')
            ->sum('subtotal');

        $catalogCount = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->count();

        // Commandes récentes
        $recentOrders = Order::where('supplier_id', $supplier->id)
            ->with(['client'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('supplier/dashboard', [
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue' => (int) $totalRevenue,
                'catalog_count' => $catalogCount,
            ],
            'recentOrders' => $recentOrders,
        ]);
    }

    /**
     * Catalogue d'articles.
     */
    public function catalog(Request $request): Response
    {
        $supplier = $request->user();
        $products = SupplierProduct::where('supplier_id', $supplier->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('supplier/catalog', [
            'products' => $products,
        ]);
    }

    /**
     * Gestion des commandes.
     */
    public function orders(Request $request): Response
    {
        $supplier = $request->user();
        $orders = Order::where('supplier_id', $supplier->id)
            ->with(['client', 'items.product'])
            ->latest()
            ->get();

        return Inertia::render('supplier/orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Visualisation des litiges.
     */
    public function litiges(Request $request): Response
    {
        $supplier = $request->user();

        // 1. Litiges sur ses commandes directes
        $orderLitiges = Order::where('supplier_id', $supplier->id)
            ->where('status', 'disputed')
            ->with(['client'])
            ->get();

        // 2. Litiges sur les chantiers/missions où il a fourni des matériaux via JCode
        $missionIds = JCode::where('fournisseur_id', $supplier->id)->pluck('mission_id')->unique();
        $missionLitiges = Mission::whereIn('id', $missionIds)
            ->where('status', 'litige')
            ->with(['client', 'artisan', 'litiges'])
            ->get();

        return Inertia::render('supplier/litiges', [
            'orderLitiges' => $orderLitiges,
            'missionLitiges' => $missionLitiges,
        ]);
    }
}
