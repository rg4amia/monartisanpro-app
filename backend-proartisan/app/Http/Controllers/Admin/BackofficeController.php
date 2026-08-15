<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewFournisseurRequest;
use App\Http\Requests\Admin\ReviewKycRequest;
use App\Http\Requests\Litige\ArbitrateLitigeRequest;
use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\User;
use App\Models\Communication;
use App\Models\Notification;
use App\Services\AdminService;
use App\Services\CommunicationService;
use App\Http\Requests\Admin\StoreCommunicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeController extends Controller
{
    public function __construct(private AdminService $adminService) {}

    public function dashboard(): Response
    {
        Inertia::share('financialKpis', $this->adminService->getFinancialKpis());
        return $this->renderPage('admin/dashboard');
    }

    public function kyc(): Response
    {
        return $this->renderPage('admin/kyc');
    }

    public function missions(): Response
    {
        return $this->renderPage('admin/missions');
    }

    public function litiges(): Response
    {
        return $this->renderPage('admin/litiges');
    }

    public function users(): Response
    {
        return $this->renderPage('admin/users');
    }

    public function transactions(): Response
    {
        Inertia::share('financialKpis', $this->adminService->getFinancialKpis());
        return $this->renderPage('admin/transactions');
    }

    public function settings(): Response
    {
        return $this->renderPage('admin/settings');
    }

    public function rolesPermissions(): Response
    {
        return $this->renderPage('admin/roles-permissions');
    }

    public function evaluations(): Response
    {
        return $this->renderPage('admin/evaluations');
    }

    public function communications(): Response
    {
        return $this->renderPage('admin/communications');
    }

    public function notifications(): Response
    {
        return $this->renderPage('admin/notifications');
    }

    public function promoCodes(): Response
    {
        return $this->renderPage('admin/promo-codes');
    }

    public function storeCommunication(StoreCommunicationRequest $request, CommunicationService $service): RedirectResponse
    {
        $service->store($request->validated(), $request->user());
        return back()->with('success', 'Communication créée en brouillon.');
    }

    public function updateCommunication(StoreCommunicationRequest $request, Communication $communication, CommunicationService $service): RedirectResponse
    {
        try {
            $service->update($communication, $request->validated());
            return back()->with('success', 'Communication modifiée.');
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroyCommunication(Communication $communication, CommunicationService $service): RedirectResponse
    {
        try {
            $service->destroy($communication);
            return back()->with('success', 'Communication supprimée.');
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function publishCommunication(Communication $communication, CommunicationService $service): RedirectResponse
    {
        try {
            $service->publish($communication);
            return back()->with('success', 'Communication publiée.');
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cloturerCommunication(Communication $communication, CommunicationService $service): RedirectResponse
    {
        try {
            $service->cloturer($communication);
            return back()->with('success', 'Communication clôturée (désactivée).');
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function toggleScoreFreeze(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'artisan') {
            return back()->with('error', 'Seuls les scores des artisans peuvent être gelés/dégelés.');
        }

        $user->update([
            'score_frozen' => !$user->score_frozen,
        ]);

        $status = $user->score_frozen ? 'gelé' : 'dégelé';
        return back()->with('success', "Le score ProsArtisan de l'artisan {$user->name} a été {$status} avec succès.");
    }

    public function llmAdmin(): Response
    {
        return $this->renderPage('admin/llm-admin');
    }

    public function reviewKyc(ReviewKycRequest $request, User $user): RedirectResponse
    {
        $this->adminService->reviewKyc(
            $request->user(),
            $user,
            $request->validated('decision'),
            $request->validated('rejection_reason'),
        );

        return back()->with('success', 'Dossier KYC traité avec succès.');
    }

    public function reviewCnmci(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'artisan') {
            return back()->with('error', 'Seuls les artisans peuvent posséder un profil CNMCI.');
        }

        $data = $request->validate([
            'decision' => ['required', 'in:valide,rejete'],
        ]);

        $user->update([
            'cnmci_status' => $data['decision'],
        ]);

        $msg = $data['decision'] === 'valide'
            ? 'Affiliation CNMCI validée avec succès.'
            : 'Affiliation CNMCI rejetée.';

        return back()->with('success', $msg);
    }

    public function resolveLitige(ArbitrateLitigeRequest $request, Litige $litige): RedirectResponse
    {
        $this->adminService->resolveLitige(
            $request->user(),
            $litige,
            $request->validated(),
        );

        return back()->with('success', 'Litige arbitré avec succès.');
    }

    public function reviewFournisseur(ReviewFournisseurRequest $request, FournisseurAgree $fournisseur): RedirectResponse
    {
        $this->adminService->reviewFournisseur(
            $request->user(),
            $fournisseur,
            $request->validated('decision'),
        );

        return back()->with('success', 'Décision fournisseur enregistrée.');
    }

    public function markNotificationRead(Notification $notification): RedirectResponse
    {
        $notification->update(['read_at' => now()]);
        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        Notification::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhereNull('user_id');
        })->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'role' => 'required|string|in:client,artisan,fournisseur,referent,admin',
            'password' => 'required|string|min:6',
            'kyc_status' => 'required|string|in:en_attente,actif,rejete',
            'account_status' => 'required|string|in:actif,suspendu',
            'score_frozen' => 'nullable|boolean',
            'device_fingerprint' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Le nom complet est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'role.required' => 'Le rôle est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['score_frozen'] = $request->boolean('score_frozen');

        User::create($validated);

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|in:client,artisan,fournisseur,referent,admin',
            'password' => 'nullable|string|min:6',
            'kyc_status' => 'required|string|in:en_attente,actif,rejete',
            'account_status' => 'required|string|in:actif,suspendu',
            'score_frozen' => 'nullable|boolean',
            'device_fingerprint' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Le nom complet est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L’adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'role.required' => 'Le rôle est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['score_frozen'] = $request->boolean('score_frozen');

        $user->update($validated);

        return back()->with('success', 'Utilisateur modifié avec succès.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte administrateur.');
        }

        $user->delete();

        return back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    public function toggleUserStatus(Request $request, User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte administrateur.');
        }

        $validated = $request->validate([
            'account_status' => 'required|string|in:actif,suspendu',
            'account_status_reason' => 'nullable|string|max:255',
        ]);

        $user->update([
            'account_status' => $validated['account_status'],
            'account_status_reason' => $validated['account_status_reason'] ?? null,
            'blocked_at' => $validated['account_status'] === 'suspendu' ? now() : null,
        ]);

        return back()->with('success', 'Statut de l’utilisateur mis à jour.');
    }

    public function updateSetting(Request $request, \App\Models\Setting $setting): RedirectResponse
    {
        $validated = $request->validate([
            'value' => 'nullable|string',
        ]);

        $setting->update([
            'value' => $validated['value'],
        ]);

        return back()->with('success', 'Paramètre mis à jour.');
    }

    public function updateSector(Request $request, \App\Models\Sector $sector): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ], [
            'name.required' => 'Le nom du secteur est obligatoire.',
        ]);

        $sector->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Secteur (catégorie) mis à jour.');
    }

    public function updateTrade(Request $request, \App\Models\Trade $trade): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ], [
            'name.required' => 'Le nom du métier est obligatoire.',
        ]);

        $trade->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Métier (sous-catégorie) mis à jour.');
    }

    public function storeSector(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:sectors,name',
        ], [
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.unique' => 'Cette catégorie existe déjà.',
        ]);

        \App\Models\Sector::create([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Nouvelle catégorie créée avec succès.');
    }

    public function storeTrade(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sector_id' => 'required|exists:sectors,id',
            'name' => 'required|string|max:100',
        ], [
            'sector_id.required' => 'La catégorie parente est obligatoire.',
            'sector_id.exists' => 'La catégorie parente sélectionnée n\'existe pas.',
            'name.required' => 'Le nom de la sous-catégorie est obligatoire.',
        ]);

        // Vérifie si la sous-catégorie existe déjà pour ce secteur
        $exists = \App\Models\Trade::where('sector_id', $validated['sector_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Cette sous-catégorie existe déjà dans cette catégorie.']);
        }

        \App\Models\Trade::create([
            'sector_id' => $validated['sector_id'],
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Nouvelle sous-catégorie créée avec succès.');
    }

    public function downloadInvoice(Litige $litige)
    {
        $payload = $litige->resolution_payload ?? [];
        $path = $payload['invoice_path'] ?? null;

        if (!$path || !file_exists($path)) {
            abort(404, "Facture de décaissement introuvable.");
        }

        return response()->download($path, "facture_decaissement_litige_{$litige->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function renderPage(string $component): Response
    {
        return Inertia::render($component, [
            'dashboard' => $this->adminService->dashboard(),
            'fournisseurs' => $this->adminService->pendingFournisseurs(60)->items(),
            'kycUsers' => $this->adminService->pendingKyc(null, 60)->items(),
            'cnmciUsers' => User::where('role', 'artisan')
                ->where('cnmci_status', 'en_attente')
                ->orderByDesc('updated_at')
                ->get(),
            'litiges' => $this->adminService->listLitiges(null, 60)->items(),
            'missions' => $this->adminService->listMissions(null, null, 100)->items(),
            'transactions' => $this->adminService->listTransactions(null, null, 100)->items(),
            'users' => $this->adminService->listUsers(null, null, null, 100)->items(),
            'evaluationsList' => $this->adminService->listEvaluations(100)->items(),
            'artisansScores' => $this->adminService->listArtisansScores(),
            'settingsList' => \App\Models\Setting::all(),
            'promoCodes' => \App\Models\PromoCode::orderByDesc('created_at')->get(),
            'sectors' => \App\Models\Sector::with('trades')->get(),
            'communications' => Communication::with('auteur:id,name,phone')
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get(),
            'adminNotifications' => Notification::where(function ($q) {
                    if (Auth::check()) {
                        $q->where('user_id', Auth::id())
                          ->orWhereNull('user_id');
                    } else {
                        $q->whereNull('user_id');
                    }
                })
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(),
            'allPermissions' => \App\Models\Permission::all(),
            'rolesPermissions' => [
                'client' => \Illuminate\Support\Facades\DB::table('permission_role')
                    ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                    ->where('permission_role.role', 'client')
                    ->pluck('permissions.name')
                    ->toArray(),
                'artisan' => \Illuminate\Support\Facades\DB::table('permission_role')
                    ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                    ->where('permission_role.role', 'artisan')
                    ->pluck('permissions.name')
                    ->toArray(),
                'fournisseur' => \Illuminate\Support\Facades\DB::table('permission_role')
                    ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                    ->where('permission_role.role', 'fournisseur')
                    ->pluck('permissions.name')
                    ->toArray(),
                'referent' => \Illuminate\Support\Facades\DB::table('permission_role')
                    ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                    ->where('permission_role.role', 'referent')
                    ->pluck('permissions.name')
                    ->toArray(),
                'admin' => \Illuminate\Support\Facades\DB::table('permission_role')
                    ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                    ->where('permission_role.role', 'admin')
                    ->pluck('permissions.name')
                    ->toArray(),
            ],
        ]);
    }

    public function aiDashboard(): Response
    {
        // 1. Statistics
        $totalCost = \Illuminate\Support\Facades\DB::table('ai_usage_logs')->sum('estimated_cost_usd') ?? 0;
        $totalRequests = \Illuminate\Support\Facades\DB::table('ai_usage_logs')->count();
        $averageResponseTime = \Illuminate\Support\Facades\DB::table('ai_usage_logs')->avg('response_time_ms') ?? 0;
        $successRate = $totalRequests > 0 
            ? (\Illuminate\Support\Facades\DB::table('ai_usage_logs')->where('status_code', 200)->count() / $totalRequests) * 100 
            : 100;

        // 2. Costs by model
        $costsByModel = \Illuminate\Support\Facades\DB::table('ai_usage_logs')
            ->select('model_name', \Illuminate\Support\Facades\DB::raw('SUM(estimated_cost_usd) as cost'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('model_name')
            ->get();

        // 3. Last 50 logs
        $logs = \Illuminate\Support\Facades\DB::table('ai_usage_logs')
            ->leftJoin('users', 'ai_usage_logs.user_id', '=', 'users.id')
            ->select('ai_usage_logs.*', 'users.email as user_email')
            ->orderBy('ai_usage_logs.created_at', 'desc')
            ->limit(50)
            ->get();

        // 4. Daily consumption for the last 30 days
        $dailyUsage = \Illuminate\Support\Facades\DB::table('ai_usage_logs')
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('SUM(estimated_cost_usd) as cost'),
                \Illuminate\Support\Facades\DB::raw('SUM(total_tokens) as tokens'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as requests')
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->limit(30)
            ->get();

        // 5. Settings
        $settings = \Illuminate\Support\Facades\DB::table('ai_settings')->pluck('value', 'key');

        Inertia::share('stats', [
            'total_cost' => (float)$totalCost,
            'total_requests' => $totalRequests,
            'avg_response_time' => round($averageResponseTime, 2),
            'success_rate' => round($successRate, 1)
        ]);
        Inertia::share('costsByModel', $costsByModel);
        Inertia::share('dailyUsage', $dailyUsage);
        Inertia::share('logs', $logs);
        Inertia::share('settings', $settings);

        return $this->renderPage('admin/ai-dashboard');
    }

    public function updateAiSettings(Request $request)
    {
        $validated = $request->validate([
            'daily_user_limit' => 'required|integer|min:0',
            'ai_enabled' => 'required|in:0,1'
        ]);

        foreach ($validated as $key => $value) {
            \Illuminate\Support\Facades\DB::table('ai_settings')
                ->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string)$value, 'updated_at' => now()]
                );
        }

        return redirect()->back()->with('success', 'Paramètres IA mis à jour avec succès.');
    }

    public function storePromoCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'min_order_amount' => 'nullable|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        \App\Models\PromoCode::create($validated);

        return back()->with('success', "Code promo {$validated['code']} créé avec succès.");
    }

    public function updatePromoCode(Request $request, \App\Models\PromoCode $promoCode): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $promoCode->id,
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'min_order_amount' => 'nullable|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', $promoCode->is_active);

        $promoCode->update($validated);

        return back()->with('success', "Code promo {$promoCode->code} mis à jour.");
    }

    public function destroyPromoCode(\App\Models\PromoCode $promoCode): RedirectResponse
    {
        $promoCode->delete();
        return back()->with('success', 'Code promo supprimé.');
    }

    public function togglePromoCode(\App\Models\PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update(['is_active' => !$promoCode->is_active]);
        $status = $promoCode->is_active ? 'activé' : 'désactivé';
        return back()->with('success', "Code promo {$promoCode->code} {$status}.");
    }
}
