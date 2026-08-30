<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Mission;
use App\Models\Devis;
use App\Models\Litige;
use App\Models\Order;
use App\Models\SupplierProduct;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        $stats = [];

        switch ($role) {
            case 'client':
                $stats = $this->getClientStats($user);
                break;
            case 'artisan':
                $stats = $this->getArtisanStats($user);
                break;
            case 'livreur':
                $stats = $this->getLivreurStats($user);
                break;
            case 'fournisseur':
                $stats = $this->getSupplierStats($user);
                break;
            default:
                $stats = [];
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    private function getClientStats($user): array
    {
        $acceptedDevisCount = Devis::where('statut', 'accepte')
            ->whereHas('mission', fn($q) => $q->where('client_id', $user->id))
            ->count();
            
        $refusedDevisCount = Devis::where('statut', 'refuse')
            ->whereHas('mission', fn($q) => $q->where('client_id', $user->id))
            ->count();

        $disputesCount = Litige::where('declencheur_id', $user->id)
            ->orWhereHas('mission', fn($q) => $q->where('client_id', $user->id))
            ->count();

        // Total spent (missions completed or funded)
        $totalSpent = Mission::where('client_id', $user->id)
            ->whereIn('status', ['financee', 'en_cours', 'terminee'])
            ->sum('montant_total');

        $activeMissionsCount = Mission::where('client_id', $user->id)
            ->whereIn('status', ['en_attente', 'financee', 'en_cours'])
            ->count();

        // Expenses by category (sum of total spent grouped by gemini_category)
        $expenses = Mission::where('client_id', $user->id)
            ->whereIn('status', ['financee', 'en_cours', 'terminee'])
            ->selectRaw('COALESCE(gemini_category, "Travaux généraux") as category, SUM(montant_total) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        // Top 3 suppliers based on ratings & completed deliveries count
        $topSuppliers = \App\Models\FournisseurAgree::join('users', 'fournisseurs_agrees.user_id', '=', 'users.id')
            ->leftJoin('evaluations', 'evaluations.evalue_id', '=', 'users.id')
            ->select('fournisseurs_agrees.nom_boutique as name')
            ->selectRaw('COALESCE(AVG(evaluations.note), 5.0) as rating')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.supplier_id = users.id AND orders.status = "delivered") as deliveries')
            ->groupBy('fournisseurs_agrees.id', 'fournisseurs_agrees.nom_boutique', 'users.id')
            ->orderByDesc('rating')
            ->take(3)
            ->get()
            ->map(fn($item) => [
                'name' => $item->name,
                'rating' => round((float) $item->rating, 1),
                'deliveries' => (int) $item->deliveries,
            ])
            ->toArray();

        // Top 3 delivery drivers based on ratings & completed delivery trips count
        $topDrivers = \App\Models\User::where('role', 'livreur')
            ->leftJoin('evaluations', 'evaluations.evalue_id', '=', 'users.id')
            ->select('users.name', 'users.id')
            ->selectRaw('COALESCE(AVG(evaluations.note), 5.0) as rating')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.driver_id = users.id AND orders.status = "delivered") as trips')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('rating')
            ->take(3)
            ->get()
            ->map(fn($item) => [
                'name' => $item->name ?? 'Livreur #' . $item->id,
                'rating' => round((float) $item->rating, 1),
                'trips' => (int) $item->trips,
            ])
            ->toArray();

        return [
            'accepted_devis_count' => $acceptedDevisCount,
            'refused_devis_count' => $refusedDevisCount,
            'disputes_count' => $disputesCount,
            'total_spent' => (int) $totalSpent,
            'active_missions_count' => $activeMissionsCount,
            'expenses_by_category' => $expenses,
            'top_suppliers' => $topSuppliers,
            'top_drivers' => $topDrivers,
        ];
    }

    private function getArtisanStats($user): array
    {
        $acceptedDevisCount = Devis::where('statut', 'accepte')
            ->where('artisan_id', $user->id)
            ->count();

        $refusedDevisCount = Devis::where('statut', 'refuse')
            ->where('artisan_id', $user->id)
            ->count();

        $disputesCount = Litige::where('declencheur_id', $user->id)
            ->orWhereHas('mission', fn($q) => $q->where('artisan_id', $user->id))
            ->count();

        $totalEarnings = Mission::where('artisan_id', $user->id)
            ->where('status', 'terminee')
            ->sum('montant_mo');

        $activeMissionsCount = Mission::where('artisan_id', $user->id)
            ->whereIn('status', ['en_attente', 'financee', 'en_cours'])
            ->count();

        $calculatedScore = app(\App\Services\ScoreService::class)->recalculateFromLedger($user);

        return [
            'accepted_devis_count' => $acceptedDevisCount,
            'refused_devis_count' => $refusedDevisCount,
            'disputes_count' => $disputesCount,
            'total_earnings' => (int) $totalEarnings,
            'active_missions_count' => $activeMissionsCount,
            'score_prosartisan' => $calculatedScore,
            'wallet_mo' => $user->wallet_mo,
        ];
    }

    private function getLivreurStats($user): array
    {
        $completedDeliveries = Order::where('driver_id', $user->id)
            ->where('status', 'delivered')
            ->count();

        $pendingDeliveries = Order::where('driver_id', $user->id)
            ->whereIn('status', ['driver_assigned', 'shipping'])
            ->count();

        $totalEarnings = Order::where('driver_id', $user->id)
            ->where('status', 'delivered')
            ->sum('delivery_cost');

        return [
            'completed_deliveries' => $completedDeliveries,
            'pending_deliveries' => $pendingDeliveries,
            'total_earnings' => (int) $totalEarnings,
        ];
    }

    private function getSupplierStats($user): array
    {
        $totalOrders = Order::where('supplier_id', $user->id)->count();
        $pendingOrders = Order::where('supplier_id', $user->id)->where('status', 'paid')->count();
        
        $totalRevenue = Order::where('supplier_id', $user->id)
            ->where('status', 'delivered')
            ->sum('subtotal');

        $catalogCount = SupplierProduct::where('supplier_id', $user->id)
            ->where('is_active', true)
            ->count();

        $recentOrders = Order::where('supplier_id', $user->id)
            ->with(['client', 'items.product'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue' => (int) $totalRevenue,
                'catalog_count' => $catalogCount,
            ],
            'recent_orders' => $recentOrders,
        ];
    }
}
