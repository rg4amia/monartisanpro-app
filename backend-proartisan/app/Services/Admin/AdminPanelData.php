<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\Communication;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\PromoCode;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrineSetting;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineVideo;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fournit les props Inertia du backoffice, onglet par onglet.
 *
 * Remplace l'ancien god method BackofficeController::renderPage() qui chargeait
 * la totalité des données de tous les onglets à chaque requête. Chaque page ne
 * reçoit désormais que sa propre tranche + les props partagées du layout.
 */
class AdminPanelData
{
    public function __construct(
        private AdminService $adminService,
        private AdminPermissionService $adminPermissions,
        private AdminObservabilityService $observability,
    ) {}

    /**
     * Props présentes sur toutes les pages admin :
     * compteurs du layout (badges de navigation) + cloche de notifications.
     */
    public function shared(): array
    {
        return [
            'dashboard' => $this->adminService->dashboard(),
            'navBadges' => $this->navBadges(),
            'adminNotifications' => $this->recentNotifications(),
        ];
    }

    /**
     * Onglet « Tableau de bord » — vue consolidée.
     * Conserve volontairement le bundle opérationnel : ses graphiques, tendances
     * et flux d'activité sont calculés côté front à partir de ces collections.
     */
    public function dashboard(): array
    {
        return [
            'financialKpis' => $this->adminService->getFinancialKpis(),
            'fournisseurs' => $this->adminService->pendingFournisseurs(60)->items(),
            'kycUsers' => $this->adminService->pendingKyc(null, 60)->items(),
            'litiges' => $this->adminService->listLitiges(null, 60)->items(),
            'missions' => $this->adminService->listMissions(null, null, 100)->items(),
            'orders' => $this->orders(),
            'transactions' => $this->adminService->listTransactions(null, null, 100)->items(),
            'users' => $this->adminService->listUsers(null, null, null, 100)->items(),
            'evaluationsList' => $this->adminService->listEvaluations(100)->items(),
            'artisansScores' => $this->adminService->listArtisansScores(),
        ];
    }

    /**
     * Onglet « KYC & Vérifications » (Chantier C4 / P1-6) — file paginée + recherche.
     */
    public function kyc(Request $request): array
    {
        $fournisseurs = $this->adminService->pendingFournisseurs(60)->items();

        return [
            'kycUsersPage' => $this->adminService->pendingKyc(
                null,
                25,
                $request->query('search_kyc') ?: null,
            )->withQueryString(),
            'pendingFournisseursList' => $fournisseurs,
            'cnmciUsers' => User::where('role', 'artisan')
                ->where('cnmci_status', 'en_attente')
                ->orderByDesc('updated_at')
                ->get(),
            'kycStats' => array_merge(
                $this->adminService->kycStats(),
                ['fournisseurs_pending' => count($fournisseurs)],
            ),
        ];
    }

    /**
     * Onglet « Missions » (Chantier C4 / P1-6) — chantiers et livraisons paginés + filtres serveur.
     */
    public function missions(Request $request): array
    {
        return [
            'missionsPage' => $this->adminService->listMissions(
                null,
                $request->query('search_mission') ?: null,
                25,
                'mission_page',
            )->withQueryString(),
            'ordersPage' => Schema::hasTable('orders')
                ? $this->adminService->listOrders(
                    $request->query('status_order') && $request->query('status_order') !== 'all' ? $request->query('status_order') : null,
                    null,
                    $request->query('search_order') ?: null,
                    25,
                    'order_page',
                )->withQueryString()
                : null,
            'missionStats' => $this->adminService->missionStats(),
            'deliveryStats' => $this->adminService->deliveryStats(),
        ];
    }

    /**
     * Onglet « Litiges » (Chantier C4 / P1-6) — liste paginée + filtres serveur.
     */
    public function litiges(Request $request): array
    {
        return [
            'litigesPage' => $this->adminService->listLitiges(
                $request->query('statut_litige') ?: null,
                20,
                $request->query('search_litige') ?: null,
            )->withQueryString(),
            'litigeStats' => $this->adminService->litigeStats(),
        ];
    }

    /**
     * Onglet « Utilisateurs » (Chantier C4 / P1-6) — liste paginée + filtres serveur.
     *
     * Les compteurs, le top artisans et la file fournisseurs proviennent de
     * requêtes dédiées : ils ne sont plus dérivés côté front d'une tranche tronquée.
     */
    public function users(Request $request): array
    {
        $stats = $this->adminService->dashboard();

        return [
            'usersPage' => $this->adminService->listUsers(
                $request->query('search_users') ?: null,
                $request->query('role_users') ?: null,
                $request->query('kyc_users') ?: null,
                25,
            )->withQueryString(),
            'userStats' => [
                'total' => $stats['users_total'],
                'artisans_actifs' => $stats['artisans_actifs'],
                'clients_actifs' => $stats['clients_actifs'],
                'fournisseurs_agrees' => $stats['fournisseurs_agrees'],
            ],
            'pendingFournisseurs' => $this->adminService->pendingFournisseurs(60)->items(),
            'topArtisans' => User::where('role', 'artisan')
                ->orderByDesc('score_prosartisan')
                ->limit(5)
                ->get(['id', 'name', 'phone', 'score_prosartisan', 'score_frozen']),
        ];
    }

    /**
     * Onglet « Évaluations & Scores » (Chantier C4 / P1-6) — listes paginées + recherche.
     */
    public function evaluations(Request $request): array
    {
        return [
            'evaluationsPage' => $this->adminService->listEvaluations(
                25,
                $request->query('search_eval') ?: null,
                'eval_page',
            )->withQueryString(),
            'artisansScoresPage' => $this->adminService->paginateArtisanScores(
                $request->query('search_score') ?: null,
                25,
                'score_page',
            )->withQueryString(),
            'evaluationStats' => $this->adminService->evaluationStats(),
            'scoreLedger' => Schema::hasTable('score_ledger_entries')
                ? $this->adminService->listScoreLedger()
                : [],
        ];
    }

    public function transactions(Request $request): array
    {
        return [
            'transactionsPage' => $this->adminService->listTransactions(
                $request->query('status_tx') ?: null,
                $request->query('provider_tx') ?: null,
                50,
                $request->query('search_tx') ?: null,
                $request->query('type_tx') ?: null,
            )->withQueryString(),
            'transactionStats' => $this->adminService->transactionStats(),
            'financialKpis' => $this->adminService->getFinancialKpis(),
        ];
    }

    public function settings(): array
    {
        return [
            'settingsList' => Schema::hasTable('settings') ? Setting::all() : [],
            'sectors' => Schema::hasTable('sectors') ? Sector::with('trades')->get() : [],
        ];
    }

    public function communications(): array
    {
        return [
            'communications' => Schema::hasTable('communications')
                ? Communication::with('auteur:id,name,phone')
                    ->orderByDesc('updated_at')
                    ->limit(100)
                    ->get()
                : [],
        ];
    }

    public function promoCodes(): array
    {
        return [
            'promoCodes' => $this->promoCodesList(),
        ];
    }

    public function observability(): array
    {
        return [
            'observability' => $this->observability->snapshot(),
        ];
    }

    public function rolesPermissions(): array
    {
        $allPermissions = [];
        $rolesPermissions = [
            'client' => [],
            'artisan' => [],
            'fournisseur' => [],
            'referent' => [],
            'livreur' => [],
            'admin' => [],
        ];

        try {
            if (Schema::hasTable('permissions') && Schema::hasTable('permission_role')) {
                // Les capacités « admin.* » ne sont pas assignables par rôle :
                // elles ont leur propre matrice (par compte admin).
                $allPermissions = Permission::where('name', 'not like', 'admin.%')->get();
                foreach (array_keys($rolesPermissions) as $role) {
                    $rolesPermissions[$role] = DB::table('permission_role')
                        ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                        ->where('permission_role.role', $role)
                        ->pluck('permissions.name')
                        ->toArray();
                }
            }
        } catch (\Throwable $e) {
            Log::error('Erreur chargement roles/permissions backoffice: '.$e->getMessage());
        }

        return [
            'allPermissions' => $allPermissions,
            'rolesPermissions' => $rolesPermissions,
            // Capacités fines du backoffice, affectées compte admin par compte admin
            // (Chantier C6 / P2-10).
            ...$this->adminPermissions->panelData(),
        ];
    }

    public function notifications(Request $request): array
    {
        if (! Schema::hasTable('notifications')) {
            return ['allNotifications' => []];
        }

        $query = Notification::with('user:id,name,phone,role');

        if ($search = $request->query('search_notification')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($role = $request->query('role_notification')) {
            $query->whereHas('user', fn ($u) => $u->where('role', $role));
        }

        if ($type = $request->query('type_notification')) {
            $query->where('type', $type);
        }

        return [
            'allNotifications' => $query->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString(),
        ];
    }

    /**
     * Onglet « Journal d'audit » (Chantier C3 / P0-4) — traçabilité des actions
     * administrateur : revue KYC, arbitrage de litige, gel de score, suspension
     * de compte, modification de paramètres, connexions admin…
     */
    public function auditLogs(Request $request): array
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return [
                'auditLogs' => ['data' => [], 'links' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 50],
                'auditActions' => [],
                'auditAdmins' => [],
            ];
        }

        $query = AdminActivityLog::query()->with('admin:id,name,phone');

        if ($search = trim((string) $request->query('search_audit', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('admin_name', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($action = $request->query('action_audit')) {
            $query->where('action', $action);
        }

        if ($adminId = $request->query('admin_audit')) {
            $query->where('admin_id', $adminId);
        }

        if ($from = $request->query('date_from_audit')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('date_to_audit')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return [
            'auditLogs' => $query->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString(),
            'auditActions' => AdminActivityLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'auditAdmins' => AdminActivityLog::query()
                ->whereNotNull('admin_id')
                ->select('admin_id', 'admin_name')
                ->distinct()
                ->orderBy('admin_name')
                ->get(),
        ];
    }

    public function vitrine(): array
    {
        $props = [
            'vitrineSlides' => [],
            'vitrineArtisanDuMois' => [],
            'vitrineArticles' => [],
            'vitrineVideos' => [],
            'vitrineFormations' => [],
            'vitrineRecrutements' => [],
            'vitrinePopups' => [],
            'vitrineSettings' => [],
            'contactMessages' => [],
        ];

        try {
            if (Schema::hasTable('vitrine_slides')) {
                $props['vitrineSlides'] = VitrineSlide::ordered()->get();
                $props['vitrineArtisanDuMois'] = VitrineArtisanDuMois::with(['user:id,name,phone,role,score_prosartisan', 'user.artisanProfile.trade'])->get();
                $props['vitrineArticles'] = VitrineArticle::with('auteur:id,name')->latest()->get();
                $props['vitrineVideos'] = VitrineVideo::ordered()->get();
                $props['vitrineFormations'] = VitrineFormation::orderBy('date_debut')->get();
                $props['vitrineRecrutements'] = VitrineRecrutement::latest()->get();
                $props['vitrinePopups'] = VitrinePopup::latest()->get();
                $props['vitrineSettings'] = VitrineSetting::all();
            }
            if (Schema::hasTable('contact_messages')) {
                $props['contactMessages'] = ContactMessage::with(['artisan:id,name,phone', 'traitePar:id,name'])->latest()->get();
            }
        } catch (\Throwable $e) {
            Log::error('Erreur chargement vitrine backoffice: '.$e->getMessage());
        }

        return $props;
    }

    /**
     * Onglet « Suivi & Coûts IA » — agrégats de la table `ai_usage_logs`.
     */
    public function aiDashboard(): array
    {
        $totalCost = DB::table('ai_usage_logs')->sum('estimated_cost_usd') ?? 0;
        $totalRequests = DB::table('ai_usage_logs')->count();
        $averageResponseTime = DB::table('ai_usage_logs')->avg('response_time_ms') ?? 0;
        $successRate = $totalRequests > 0
            ? (DB::table('ai_usage_logs')->where('status_code', 200)->count() / $totalRequests) * 100
            : 100;

        $costsByModel = DB::table('ai_usage_logs')
            ->select('model_name', DB::raw('SUM(estimated_cost_usd) as cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('model_name')
            ->get();

        $logs = DB::table('ai_usage_logs')
            ->leftJoin('users', 'ai_usage_logs.user_id', '=', 'users.id')
            ->select('ai_usage_logs.*', 'users.email as user_email')
            ->orderBy('ai_usage_logs.created_at', 'desc')
            ->limit(50)
            ->get();

        $dailyUsage = DB::table('ai_usage_logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(estimated_cost_usd) as cost'),
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('COUNT(*) as requests')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->limit(30)
            ->get();

        return [
            'stats' => [
                'total_cost' => (float) $totalCost,
                'total_requests' => $totalRequests,
                'avg_response_time' => round($averageResponseTime, 2),
                'success_rate' => round($successRate, 1),
            ],
            'costsByModel' => $costsByModel,
            'dailyUsage' => $dailyUsage,
            'logs' => $logs,
            'settings' => DB::table('ai_settings')->pluck('value', 'key'),
        ];
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Compteurs affichés en badge dans la navigation latérale, présents sur
     * toutes les pages. Requêtes COUNT légères (l'ancien code dérivait ces
     * nombres de collections complètes envoyées à chaque onglet).
     */
    private function navBadges(): array
    {
        return [
            'transactions_en_attente' => Schema::hasTable('transactions')
                ? Transaction::where('statut', 'en_attente')->count() : 0,
            'communications_publiees' => Schema::hasTable('communications')
                ? Communication::where('statut', 'publie')->count() : 0,
            'promo_codes_actifs' => Schema::hasTable('promo_codes')
                ? PromoCode::where('is_active', true)->count() : 0,
            'contact_messages_nouveaux' => Schema::hasTable('contact_messages')
                ? ContactMessage::where('statut', 'nouveau')->count() : 0,
        ];
    }

    private function recentNotifications(): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        return Notification::where(function ($q) {
            if (Auth::check()) {
                $q->where('user_id', Auth::id())->orWhereNull('user_id');
            } else {
                $q->whereNull('user_id');
            }
        })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->toArray();
    }

    private function orders(): array
    {
        try {
            return Schema::hasTable('orders')
                ? $this->adminService->listOrders(null, null, null, 150)->items()
                : [];
        } catch (\Throwable $e) {
            Log::error('Erreur chargement orders backoffice: '.$e->getMessage());

            return [];
        }
    }

    private function promoCodesList()
    {
        try {
            return Schema::hasTable('promo_codes')
                ? PromoCode::orderByDesc('created_at')->get()
                : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
