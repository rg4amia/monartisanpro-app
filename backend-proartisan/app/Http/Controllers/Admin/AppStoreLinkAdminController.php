<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppStoreLink;
use App\Services\Admin\AppStoreLinkAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Liens Google Play et App Store du site vitrine (Chantier 16). Capacité
 * `admin.vitrine.manage` portée par les routes.
 */
class AppStoreLinkAdminController extends Controller
{
    public function __construct(private AppStoreLinkAdminService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in(array_keys(AppStoreLink::PLATFORM_LABELS))],
            'url' => 'required|string|max:500',
        ], [
            'platform.required' => 'Choisissez le magasin d\'applications.',
            'platform.in' => 'Choisissez Google Play ou App Store.',
            'url.required' => 'Indiquez l\'adresse du lien.',
        ]);

        $this->service->create($validated['platform'], $validated['url'], $request->user());

        return back()->with('success', 'Lien créé en brouillon : validez-le pour l\'afficher sur le site.');
    }

    public function update(Request $request, AppStoreLink $link): RedirectResponse
    {
        $validated = $request->validate(['url' => 'required|string|max:500'], [
            'url.required' => 'Indiquez l\'adresse du lien.',
        ]);

        $this->service->update($link, $validated['url'], $request->user());

        return back()->with('success', 'Lien modifié.');
    }

    public function publish(Request $request, AppStoreLink $link): RedirectResponse
    {
        $this->service->publish($link, $request->user());

        return back()->with('success', 'Lien validé : il est affiché sur le site.');
    }

    public function disable(Request $request, AppStoreLink $link): RedirectResponse
    {
        $this->service->disable($link, $request->user());

        return back()->with('success', 'Lien désactivé : il n\'est plus affiché sur le site.');
    }

    public function destroy(Request $request, AppStoreLink $link): RedirectResponse
    {
        $this->service->delete($link, $request->user());

        return back()->with('success', 'Lien supprimé.');
    }
}
