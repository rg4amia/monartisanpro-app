<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqAdminController extends Controller
{
    private const ROLES = ['client', 'artisan', 'livreur', 'fournisseur'];

    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $this->validated($request);

            Faq::create($validated);

            return back()->with('success', 'Question ajoutée à la FAQ.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeFaq: '.$e->getMessage());

            return back()->withErrors(['faq' => "Impossible de créer la question : ".$e->getMessage()]);
        }
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        try {
            $faq->update($this->validated($request));

            return back()->with('success', 'Question mise à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateFaq: '.$e->getMessage());

            return back()->withErrors(['faq' => "Impossible de mettre à jour la question : ".$e->getMessage()]);
        }
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'Question supprimée.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'reponse' => 'required|string',
            'categorie' => 'nullable|string|max:100',
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:'.implode(',', self::ROLES),
            'ordre' => 'nullable|integer|min:0',
            'actif' => 'nullable',
        ]);

        $validated['ordre'] = (int) ($validated['ordre'] ?? 0);
        $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

        return $validated;
    }
}
