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
        ]);
    }
}
