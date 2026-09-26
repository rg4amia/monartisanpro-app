<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Missions dont le séquestre est engagé — base des montants cumulés.
     *
     * La migration FSM de juillet a converti les statuts français en états
     * anglais (`financee` → `funded_locked`, `en_cours` → `in_progress`,
     * `terminee` → `completed`). Les filtres de ce contrôleur étaient restés
     * en français : depuis, tous les cumuls du tableau de bord renvoyaient
     * zéro. Correspondance alignée sur `MissionController::index()`.
     */
    private const ENGAGED_MISSION_STATES = [
        'funded_locked',
        'in_progress',
        'pending_approval',
        'completed',
    ];

    /**
     * Libellés des classes de véhicule (`orders.vehicle_class`).
     *
     * L'écran client attend une clé `vehicle` pour chaque livreur ; elle
     * n'était pas produite, et le mobile la transtypait sans filet.
     */
    private const VEHICLE_LABELS = [
        'moto' => 'Moto',
        'voiture' => 'Voiture',
        'cargo' => 'Cargo',
    ];

    /** Missions en cours de vie, du brouillon à la validation du dernier jalon. */
    private const ACTIVE_MISSION_STATES = [
        'draft',
        'pending_artisan_acceptance',
        'pending_funding',
        'funded_locked',
        'in_progress',
        'pending_approval',
    ];

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

    /**
     * Note moyenne d'un acteur, ou `null` s'il n'a jamais été évalué.
     *
     * Ces classements appliquaient auparavant `COALESCE(AVG(note), 5.0)` :
     * un compte sans la moindre évaluation héritait donc de la note maximale.
     * Un palmarès « les mieux notés » où chacun démarre à 5/5 ne classe rien
     * et attribue une réputation que personne n'a méritée. L'absence de note
     * doit se dire, pas se combler.
     */
    private function averageRating(object $row): ?float
    {
        return (int) $row->ratings_count > 0
            ? round((float) $row->rating, 1)
            : null;
    }

    private function getClientStats($user): array
    {
        $acceptedDevisCount = Devis::where('statut', 'accepte')
            ->whereHas('mission', fn ($q) => $q->where('client_id', $user->id))
            ->count();

        $refusedDevisCount = Devis::where('statut', 'refuse')
            ->whereHas('mission', fn ($q) => $q->where('client_id', $user->id))
            ->count();

        $disputesCount = Litige::where('declencheur_id', $user->id)
            ->orWhereHas('mission', fn ($q) => $q->where('client_id', $user->id))
            ->count();

        // Total spent (missions completed or funded)
        $totalSpent = Mission::where('client_id', $user->id)
            ->whereIn('status', self::ENGAGED_MISSION_STATES)
            ->sum('montant_total');

        $activeMissionsCount = Mission::where('client_id', $user->id)
            ->whereIn('status', self::ACTIVE_MISSION_STATES)
            ->count();

        // Expenses by category (sum of total spent grouped by gemini_category)
        $expenses = Mission::where('client_id', $user->id)
            ->whereIn('status', self::ENGAGED_MISSION_STATES)
            ->selectRaw('COALESCE(gemini_category, "Travaux généraux") as category, SUM(montant_total) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            // SUM() renvoie un DECIMAL, transmis en chaîne par PDO sous MariaDB
            // (production) : sans ce cast, l'app recevait "150000" au lieu de 150000.
            ->map(fn ($total) => (int) $total)
            ->toArray();

        // Top 3 suppliers based on ratings & completed deliveries count
        $topSuppliers = FournisseurAgree::join('users', 'fournisseurs_agrees.user_id', '=', 'users.id')
            ->leftJoin('evaluations', 'evaluations.evalue_id', '=', 'users.id')
            ->select('fournisseurs_agrees.nom_boutique as name')
            ->selectRaw('AVG(evaluations.note) as rating')
            ->selectRaw('COUNT(evaluations.id) as ratings_count')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.supplier_id = users.id AND orders.status = "delivered") as deliveries')
            ->groupBy('fournisseurs_agrees.id', 'fournisseurs_agrees.nom_boutique', 'users.id')
            // Les évalués d'abord : sans cela, les comptes sans la moindre
            // évaluation se mélangeaient aux mieux notés.
            ->orderByRaw('COUNT(evaluations.id) = 0')
            ->orderByDesc('rating')
            ->take(3)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->name,
                'rating' => $this->averageRating($item),
                'ratings_count' => (int) $item->ratings_count,
                'deliveries' => (int) $item->deliveries,
            ])
            ->toArray();

        // Top 3 delivery drivers based on ratings & completed delivery trips count
        $topDrivers = User::where('role', 'livreur')
            ->leftJoin('evaluations', 'evaluations.evalue_id', '=', 'users.id')
            ->select('users.name', 'users.id')
            ->selectRaw('AVG(evaluations.note) as rating')
            ->selectRaw('COUNT(evaluations.id) as ratings_count')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.driver_id = users.id AND orders.status = "delivered") as trips')
            // Le véhicule n'est pas porté par le compte livreur mais par
            // chaque commande : on retient sa classe la plus fréquente.
            ->selectRaw('(SELECT o.vehicle_class FROM orders o WHERE o.driver_id = users.id AND o.status = "delivered" GROUP BY o.vehicle_class ORDER BY COUNT(*) DESC LIMIT 1) as vehicle_class')
            ->groupBy('users.id', 'users.name')
            ->orderByRaw('COUNT(evaluations.id) = 0')
            ->orderByDesc('rating')
            ->take(3)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->name ?? 'Livreur #'.$item->id,
                'rating' => $this->averageRating($item),
                'ratings_count' => (int) $item->ratings_count,
                'trips' => (int) $item->trips,
                'vehicle' => self::VEHICLE_LABELS[$item->vehicle_class] ?? 'Moto',
            ])
            ->toArray();

        return [
            'accepted_devis_count' => $acceptedDevisCount,
            'refused_devis_count' => $refusedDevisCount,
            'disputes_count' => $disputesCount,
            'total_spent' => (int) $totalSpent,
            'active_missions_count' => $activeMissionsCount,
            // Casté en objet : un tableau associatif PHP vide se sérialise en
            // `[]` (tableau JSON) et non `{}`, ce qui faisait échouer le
            // transtypage côté mobile pour tout client sans mission.
            'expenses_by_category' => (object) $expenses,
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
            ->orWhereHas('mission', fn ($q) => $q->where('artisan_id', $user->id))
            ->count();

        $totalEarnings = Mission::where('artisan_id', $user->id)
            ->where('status', 'completed')
            ->sum('montant_mo');

        $activeMissionsCount = Mission::where('artisan_id', $user->id)
            ->whereIn('status', self::ACTIVE_MISSION_STATES)
            ->count();

        $calculatedScore = app(ScoreService::class)->recalculateFromLedger($user);

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

        $earnings = app(OrderService::class)->driverEarningsSummary($user);
        $ratings = app(ScoreService::class)->ratingsSummary($user);
        $calculatedScore = app(ScoreService::class)->recalculateFromLedger($user);

        return [
            'completed_deliveries' => $completedDeliveries,
            'pending_deliveries' => $earnings['pending_count'],
            'total_earnings' => $earnings['earned'],
            'pending_earnings' => $earnings['pending'],
            // Courses livrées dont le client n'a pas encore réglé le montant.
            'awaiting_payment_earnings' => $earnings['awaiting_payment'],
            'awaiting_payment_count' => $earnings['awaiting_payment_count'],
            'wallet_mo' => $user->wallet_mo,
            'rating' => $ratings['rating'],
            'ratings_count' => $ratings['ratings_count'],
            'ratings_distribution' => $ratings['distribution'],
            'score_prosartisan' => $calculatedScore,
        ];
    }

    private function getSupplierStats($user): array
    {
        $totalOrders = Order::visibleToSupplier()->where('supplier_id', $user->id)->count();
        $pendingOrders = Order::visibleToSupplier()->where('supplier_id', $user->id)->where('status', 'paid')->count();

        $totalRevenue = Order::visibleToSupplier()->where('supplier_id', $user->id)
            ->where('status', 'delivered')
            ->sum('subtotal');

        $catalogCount = SupplierProduct::where('supplier_id', $user->id)
            ->where('is_active', true)
            ->count();

        $recentOrders = Order::visibleToSupplier()->where('supplier_id', $user->id)
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
