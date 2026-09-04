<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReviewKycRequest;
use App\Http\Requests\Admin\BulkUserStatusRequest;
use App\Http\Requests\Admin\ReviewCnmciRequest;
use App\Http\Requests\Admin\ReviewFournisseurRequest;
use App\Http\Requests\Admin\ReviewKycRequest;
use App\Http\Requests\Admin\StoreCommunicationRequest;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\StoreSectorRequest;
use App\Http\Requests\Admin\StoreTradeRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\SyncAdminPermissionsRequest;
use App\Http\Requests\Admin\ToggleUserStatusRequest;
use App\Http\Requests\Admin\UpdateAiSettingsRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Http\Requests\Admin\UpdateTradeRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Litige\ArbitrateLitigeRequest;
use App\Models\Communication;
use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\Notification;
use App\Models\PromoCode;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Trade;
use App\Models\User;
use App\Services\Admin\AdminExportService;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminGdprService;
use App\Services\Admin\AdminPanelData;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPromoCodeService;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AdminTaxonomyService;
use App\Services\Admin\AdminUserService;
use App\Services\AdminService;
use App\Services\CommunicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackofficeController extends Controller
{
    public function __construct(
        private AdminService $adminService,
        private AdminPanelData $panelData,
    ) {}

    /**
     * Rend une page du backoffice avec ses props spécifiques + les props
     * partagées du layout (badges de navigation, cloche de notifications).
     */
    private function page(string $component, array $props = []): Response
    {
        return Inertia::render($component, array_merge($this->panelData->shared(), $props));
    }

    public function dashboard(): Response
    {
        return $this->page('admin/dashboard', $this->panelData->dashboard());
    }

    public function kyc(Request $request): Response
    {
        return $this->page('admin/kyc', $this->panelData->kyc($request));
    }

    public function missions(Request $request): Response
    {
        return $this->page('admin/missions', $this->panelData->missions($request));
    }

    public function litiges(Request $request): Response
    {
        return $this->page('admin/litiges', $this->panelData->litiges($request));
    }

    public function users(Request $request): Response
    {
        return $this->page('admin/users', $this->panelData->users($request));
    }

    public function transactions(Request $request): Response
    {
        return $this->page('admin/transactions', $this->panelData->transactions($request));
    }

    public function settings(): Response
    {
        return $this->page('admin/settings', $this->panelData->settings());
    }

    public function rolesPermissions(): Response
    {
        return $this->page('admin/roles-permissions', $this->panelData->rolesPermissions());
    }

    public function evaluations(Request $request): Response
    {
        return $this->page('admin/evaluations', $this->panelData->evaluations($request));
    }

    public function personalData(User $user, AdminGdprService $gdpr): JsonResponse
    {
        return response()->json($gdpr->personalData($user));
    }

    public function exportPersonalData(User $user, AdminGdprService $gdpr): StreamedResponse
    {
        $data = $gdpr->personalData($user);
        $filename = sprintf('donnees_personnelles_user_%d_%s.json', $user->id, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function anonymizeUser(Request $request, User $user, AdminGdprService $gdpr): RedirectResponse
    {
        try {
            $gdpr->anonymize($user, $request->user());
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Compte #{$user->id} anonymisé (RGPD).");
    }

    public function syncAdminPermissions(SyncAdminPermissionsRequest $request, User $user, AdminPermissionService $permissions): RedirectResponse
    {
        try {
            $permissions->sync($user, $request->validated('capabilities'), $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', "Droits de {$user->name} mis à jour.");
    }

    public function communications(): Response
    {
        return $this->page('admin/communications', $this->panelData->communications());
    }

    public function notifications(Request $request): Response
    {
        return $this->page('admin/notifications', $this->panelData->notifications($request));
    }

    public function promoCodes(): Response
    {
        return $this->page('admin/promo-codes', $this->panelData->promoCodes());
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

    public function toggleScoreFreeze(User $user, AdminUserService $users): RedirectResponse
    {
        try {
            $frozen = $users->toggleScoreFreeze($user);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        $status = $frozen ? 'gelé' : 'dégelé';

        return back()->with('success', "Le score ProsArtisan de l'artisan {$user->name} a été {$status} avec succès.");
    }

    public function llmAdmin(): Response
    {
        // Le panneau LLM charge ses données via ses propres endpoints XHR (api/llm/*).
        return $this->page('admin/llm-admin');
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

    public function bulkReviewKyc(BulkReviewKycRequest $request): RedirectResponse
    {
        $count = $this->adminService->bulkReviewKyc(
            $request->user(),
            $request->validated('user_ids'),
            $request->validated('decision'),
            $request->validated('rejection_reason'),
        );

        return back()->with('success', "{$count} dossier(s) KYC traité(s).");
    }

    public function bulkUserStatus(BulkUserStatusRequest $request, AdminUserService $users): RedirectResponse
    {
        $count = $users->bulkToggleStatus($request->validated('user_ids'), $request->validated());

        return back()->with('success', "{$count} compte(s) mis à jour.");
    }

    public function reviewCnmci(ReviewCnmciRequest $request, User $user, AdminUserService $users): RedirectResponse
    {
        try {
            $users->reviewCnmci($user, $request->validated('decision'));
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $request->validated('decision') === 'valide'
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

    public function storeUser(StoreUserRequest $request, AdminUserService $users): RedirectResponse
    {
        $users->create($request->validated());

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function updateUser(UpdateUserRequest $request, User $user, AdminUserService $users): RedirectResponse
    {
        $users->update($user, $request->validated());

        return back()->with('success', 'Utilisateur modifié avec succès.');
    }

    public function destroyUser(User $user, AdminUserService $users): RedirectResponse
    {
        try {
            $users->delete($user);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Utilisateur supprimé avec succès.');
    }

    public function toggleUserStatus(ToggleUserStatusRequest $request, User $user, AdminUserService $users): RedirectResponse
    {
        try {
            $users->toggleStatus($user, $request->validated());
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Statut de l’utilisateur mis à jour.');
    }

    public function updateSetting(UpdateSettingRequest $request, Setting $setting, AdminSettingsService $settings): RedirectResponse
    {
        $settings->updateSetting($setting, $request->validated('value'));

        return back()->with('success', 'Paramètre mis à jour.');
    }

    public function updateSector(StoreSectorRequest $request, Sector $sector, AdminTaxonomyService $taxonomy): RedirectResponse
    {
        $taxonomy->updateSector($sector, $request->validated('name'));

        return back()->with('success', 'Secteur (catégorie) mis à jour.');
    }

    public function updateTrade(UpdateTradeRequest $request, Trade $trade, AdminTaxonomyService $taxonomy): RedirectResponse
    {
        $taxonomy->updateTrade($trade, $request->validated('name'));

        return back()->with('success', 'Métier (sous-catégorie) mis à jour.');
    }

    public function storeSector(StoreSectorRequest $request, AdminTaxonomyService $taxonomy): RedirectResponse
    {
        $taxonomy->createSector($request->validated('name'));

        return back()->with('success', 'Nouvelle catégorie créée avec succès.');
    }

    public function storeTrade(StoreTradeRequest $request, AdminTaxonomyService $taxonomy): RedirectResponse
    {
        $taxonomy->createTrade($request->validated());

        return back()->with('success', 'Nouvelle sous-catégorie créée avec succès.');
    }

    public function downloadInvoice(Litige $litige)
    {
        $payload = $litige->resolution_payload ?? [];
        $path = $payload['invoice_path'] ?? null;

        if (! $path || ! file_exists($path)) {
            abort(404, 'Facture de décaissement introuvable.');
        }

        return response()->download($path, "facture_decaissement_litige_{$litige->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function aiDashboard(): Response
    {
        return $this->page('admin/ai-dashboard', $this->panelData->aiDashboard());
    }

    public function updateAiSettings(UpdateAiSettingsRequest $request, AdminSettingsService $settings): RedirectResponse
    {
        $settings->updateAiSettings($request->validated());

        return back()->with('success', 'Paramètres IA mis à jour avec succès.');
    }

    public function storePromoCode(StorePromoCodeRequest $request, AdminPromoCodeService $promoCodes): RedirectResponse
    {
        $promoCode = $promoCodes->create($request->validated());

        return back()->with('success', "Code promo {$promoCode->code} créé avec succès.");
    }

    public function updatePromoCode(StorePromoCodeRequest $request, PromoCode $promoCode, AdminPromoCodeService $promoCodes): RedirectResponse
    {
        $promoCodes->update($promoCode, $request->validated());

        return back()->with('success', "Code promo {$promoCode->code} mis à jour.");
    }

    public function destroyPromoCode(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->delete();

        return back()->with('success', 'Code promo supprimé.');
    }

    public function togglePromoCode(PromoCode $promoCode, AdminPromoCodeService $promoCodes): RedirectResponse
    {
        $active = $promoCodes->toggle($promoCode);
        $status = $active ? 'activé' : 'désactivé';

        return back()->with('success', "Code promo {$promoCode->code} {$status}.");
    }

    public function vitrine(): Response
    {
        return $this->page('admin/vitrine', $this->panelData->vitrine());
    }

    public function auditLogs(Request $request): Response
    {
        return $this->page('admin/audit-logs', $this->panelData->auditLogs($request));
    }

    public function observability(): Response
    {
        return $this->page('admin/observability', $this->panelData->observability());
    }

    public function retryFailedJobs(Request $request, AdminActivityLogger $audit): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        $audit->log('observability.jobs_retried', null, [], actor: $request->user());

        return back()->with('success', 'Jobs en échec relancés.');
    }

    public function flushFailedJobs(Request $request, AdminActivityLogger $audit): RedirectResponse
    {
        Artisan::call('queue:flush');
        $audit->log('observability.jobs_flushed', null, [], actor: $request->user());

        return back()->with('success', 'File des jobs en échec purgée.');
    }

    public function exportCsv(Request $request, string $resource, AdminExportService $exports): StreamedResponse
    {
        abort_unless(in_array($resource, AdminExportService::RESOURCES, true), 404);

        return $exports->stream($resource, $request);
    }
}
