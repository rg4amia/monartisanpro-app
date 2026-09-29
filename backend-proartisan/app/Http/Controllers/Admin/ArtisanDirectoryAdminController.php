<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtisanAvailability;
use App\Models\User;
use App\Services\Admin\ArtisanDirectoryAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Annuaire artisans du backoffice (Chantier 15). Capacité
 * `admin.directory.manage` portée par les routes.
 */
class ArtisanDirectoryAdminController extends Controller
{
    public function __construct(private ArtisanDirectoryAdminService $service) {}

    public function approve(Request $request, ArtisanAvailability $availability): RedirectResponse
    {
        $this->service->approve($availability, $request->user());

        return back()->with('success', 'Disponibilité validée : elle est publiée dans l\'annuaire.');
    }

    public function reject(Request $request, ArtisanAvailability $availability): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Indiquez le motif du refus.',
            'reason.min' => 'Le motif doit compter au moins 5 caractères.',
        ]);

        $this->service->reject($availability, $validated['reason'], $request->user());

        return back()->with('success', 'Disponibilité refusée : l\'artisan en est informé.');
    }

    public function setAvailability(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'until_date' => 'nullable|date',
            'schedule' => 'nullable|array',
            'schedule.*.day' => 'required|integer',
            'schedule.*.start' => 'required|string',
            'schedule.*.end' => 'required|string',
            'night_work' => 'nullable|boolean',
        ]);

        $this->service->setAvailability($user, $validated, $request->user());

        return back()->with('success', 'Disponibilité enregistrée et publiée.');
    }

    public function hide(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Indiquez le motif du retrait.',
            'reason.min' => 'Le motif doit compter au moins 5 caractères.',
        ]);

        $this->service->hide($user, $validated['reason'], $request->user());

        return back()->with('success', 'Fiche retirée de l\'annuaire.');
    }

    public function show(Request $request, User $user): RedirectResponse
    {
        $this->service->show($user, $request->user());

        return back()->with('success', 'Fiche de nouveau active dans l\'annuaire.');
    }
}
