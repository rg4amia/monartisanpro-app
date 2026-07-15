<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewFournisseurRequest;
use App\Http\Requests\Admin\ReviewKycRequest;
use App\Http\Requests\Litige\ArbitrateLitigeRequest;
use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\User;
use App\Services\AdminService;
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

    public function downloadInvoice(Litige $litige)
    {
        $payload = $litige->resolution_payload ?? [];
        $path = $payload['invoice_path'] ?? null;

        if (!$path || !file_exists($path)) {
            abort(404, "Facture de décaissement introuvable.");
        }

        return response()->download($path, "facture_decaissement_litige_{$litige->id}.pdf");
    }

    private function renderPage(string $component): Response
    {
        return Inertia::render($component, [
            'dashboard' => $this->adminService->dashboard(),
            'fournisseurs' => $this->adminService->pendingFournisseurs(60)->items(),
            'kycUsers' => $this->adminService->pendingKyc(null, 60)->items(),
            'litiges' => $this->adminService->listLitiges(null, 60)->items(),
            'missions' => $this->adminService->listMissions(null, null, 100)->items(),
            'transactions' => $this->adminService->listTransactions(null, null, 100)->items(),
            'users' => $this->adminService->listUsers(null, null, null, 100)->items(),
            'settingsList' => \App\Models\Setting::all(),
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
}
