<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineVideo;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VitrineAdminController extends Controller
{
    // =========================================================================
    // 1. SLIDES HERO
    // =========================================================================

    public function storeSlide(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'sous_titre' => 'nullable|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'cta_texte' => 'nullable|string|max:100',
                'cta_lien' => 'nullable|string|max:255',
                'ordre' => 'nullable|integer|min:0',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/slides', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);
            $validated['ordre'] = (int) ($validated['ordre'] ?? 0);
            $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

            VitrineSlide::create($validated);

            return back()->with('success', 'Slide créé avec succès.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeSlide: ' . $e->getMessage());
            return back()->withErrors(['slide' => 'Impossible de créer le slide : ' . $e->getMessage()]);
        }
    }

    public function updateSlide(Request $request, VitrineSlide $slide): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'sous_titre' => 'nullable|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'cta_texte' => 'nullable|string|max:100',
                'cta_lien' => 'nullable|string|max:255',
                'ordre' => 'nullable|integer|min:0',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/slides', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);
            if (isset($validated['ordre'])) {
                $validated['ordre'] = (int) $validated['ordre'];
            }
            if ($request->has('actif')) {
                $validated['actif'] = $request->boolean('actif');
            }

            $slide->update($validated);

            return back()->with('success', 'Slide mis à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateSlide: ' . $e->getMessage());
            return back()->withErrors(['slide' => 'Impossible de mettre à jour le slide : ' . $e->getMessage()]);
        }
    }

    public function destroySlide(VitrineSlide $slide): RedirectResponse
    {
        $slide->delete();
        return back()->with('success', 'Slide supprimé.');
    }

    // =========================================================================
    // 2. ARTISAN DU MOIS
    // =========================================================================

    public function storeArtisanDuMois(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'mois' => 'required|string',
                'photo' => 'nullable|file|max:10240',
                'photo_override_url' => 'nullable|string',
                'texte_editorial' => 'nullable|string',
                'actif' => 'nullable',
            ]);

            $moisInput = $request->input('mois');
            try {
                $parsedMois = \Carbon\Carbon::parse($moisInput)->startOfMonth()->toDateString();
            } catch (\Throwable $e) {
                $parsedMois = now()->startOfMonth()->toDateString();
            }

            $photoOverrideUrl = $request->input('photo_override_url') ?: null;

            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $path = $request->file('photo')->store('vitrine/artisans', 'public');
                $photoOverrideUrl = '/storage/' . $path;
            }

            $actif = $request->has('actif') ? $request->boolean('actif') : true;

            VitrineArtisanDuMois::updateOrCreate(
                ['mois' => $parsedMois],
                [
                    'user_id' => (int) $request->input('user_id'),
                    'photo_override_url' => $photoOverrideUrl,
                    'texte_editorial' => $request->input('texte_editorial') ?: null,
                    'actif' => $actif,
                ]
            );

            return back()->with('success', 'Artisan du mois configuré avec succès.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeArtisanDuMois: ' . $e->getMessage());
            return back()->withErrors(['artisan_du_mois' => 'Impossible de configurer l\'artisan du mois : ' . $e->getMessage()]);
        }
    }

    public function destroyArtisanDuMois(VitrineArtisanDuMois $adm): RedirectResponse
    {
        $adm->delete();
        return back()->with('success', 'Artisan du mois supprimé.');
    }

    // =========================================================================
    // 3. ARTICLES / ACTUALITÉS
    // =========================================================================

    public function storeArticle(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'contenu' => 'required|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'categorie' => 'required|in:actualite,evenement,temoignage,partenariat',
                'publie' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/articles', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);

            $validated['auteur_id'] = $request->user()->id;
            $validated['slug'] = Str::slug($validated['titre']) . '-' . Str::random(5);
            $validated['publie'] = $request->has('publie') ? $request->boolean('publie') : true;

            if ($validated['publie']) {
                $validated['publie_at'] = now();
            }

            VitrineArticle::create($validated);

            return back()->with('success', 'Article créé avec succès.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeArticle: ' . $e->getMessage());
            return back()->withErrors(['article' => 'Impossible de créer l\'article : ' . $e->getMessage()]);
        }
    }

    public function updateArticle(Request $request, VitrineArticle $article): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'contenu' => 'required|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'categorie' => 'required|in:actualite,evenement,temoignage,partenariat',
                'publie' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/articles', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);

            if ($request->has('publie')) {
                $validated['publie'] = $request->boolean('publie');
                if ($validated['publie'] && !$article->publie) {
                    $validated['publie_at'] = now();
                } elseif (!$validated['publie']) {
                    $validated['publie_at'] = null;
                }
            }

            $article->update($validated);

            return back()->with('success', 'Article mis à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateArticle: ' . $e->getMessage());
            return back()->withErrors(['article' => 'Impossible de mettre à jour l\'article : ' . $e->getMessage()]);
        }
    }

    public function destroyArticle(VitrineArticle $article): RedirectResponse
    {
        $article->delete();
        return back()->with('success', 'Article supprimé.');
    }

    // =========================================================================
    // 4. CAPSULES VIDÉO
    // =========================================================================

    public function storeVideo(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video_url' => 'required|string',
                'thumbnail' => 'nullable|file|max:10240',
                'thumbnail_url' => 'nullable|string',
                'categorie' => 'required|in:capsule,formation,temoignage,evenement',
                'ordre' => 'nullable|integer|min:0',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
                $path = $request->file('thumbnail')->store('vitrine/videos', 'public');
                $validated['thumbnail_url'] = '/storage/' . $path;
            }

            unset($validated['thumbnail']);
            $validated['ordre'] = (int) ($validated['ordre'] ?? 0);
            $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

            VitrineVideo::create($validated);

            return back()->with('success', 'Vidéo ajoutée.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeVideo: ' . $e->getMessage());
            return back()->withErrors(['video' => 'Impossible d\'ajouter la vidéo : ' . $e->getMessage()]);
        }
    }

    public function updateVideo(Request $request, VitrineVideo $video): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'nullable|string',
                'video_url' => 'required|string',
                'thumbnail' => 'nullable|file|max:10240',
                'thumbnail_url' => 'nullable|string',
                'categorie' => 'required|in:capsule,formation,temoignage,evenement',
                'ordre' => 'nullable|integer|min:0',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
                $path = $request->file('thumbnail')->store('vitrine/videos', 'public');
                $validated['thumbnail_url'] = '/storage/' . $path;
            }

            unset($validated['thumbnail']);
            if (isset($validated['ordre'])) {
                $validated['ordre'] = (int) $validated['ordre'];
            }
            if ($request->has('actif')) {
                $validated['actif'] = $request->boolean('actif');
            }

            $video->update($validated);

            return back()->with('success', 'Vidéo mise à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateVideo: ' . $e->getMessage());
            return back()->withErrors(['video' => 'Impossible de mettre à jour la vidéo : ' . $e->getMessage()]);
        }
    }

    public function destroyVideo(VitrineVideo $video): RedirectResponse
    {
        $video->delete();
        return back()->with('success', 'Vidéo supprimée.');
    }

    // =========================================================================
    // 5. FORMATIONS
    // =========================================================================

    public function storeFormation(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'date_debut' => 'required|date',
                'date_fin' => 'nullable|date',
                'lieu' => 'required|string|max:255',
                'formateur' => 'nullable|string|max:255',
                'places_total' => 'nullable|integer|min:0',
                'tarif' => 'required|integer|min:0',
                'lien_inscription' => 'nullable|string|max:255',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/formations', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);

            $validated['places_total'] = isset($validated['places_total']) ? (int) $validated['places_total'] : null;
            $validated['places_restantes'] = $validated['places_total'];
            $validated['tarif'] = (int) $validated['tarif'];
            $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

            VitrineFormation::create($validated);

            return back()->with('success', 'Formation créée.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeFormation: ' . $e->getMessage());
            return back()->withErrors(['formation' => 'Impossible de créer la formation : ' . $e->getMessage()]);
        }
    }

    public function updateFormation(Request $request, VitrineFormation $formation): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'date_debut' => 'required|date',
                'date_fin' => 'nullable|date',
                'lieu' => 'required|string|max:255',
                'formateur' => 'nullable|string|max:255',
                'places_total' => 'nullable|integer|min:0',
                'tarif' => 'required|integer|min:0',
                'lien_inscription' => 'nullable|string|max:255',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/formations', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);

            if (isset($validated['places_total'])) {
                $newTotal = (int) $validated['places_total'];
                $difference = $newTotal - ($formation->places_total ?? 0);
                $validated['places_total'] = $newTotal;
                $validated['places_restantes'] = max(0, ($formation->places_restantes ?? 0) + $difference);
            }
            if (isset($validated['tarif'])) {
                $validated['tarif'] = (int) $validated['tarif'];
            }
            if ($request->has('actif')) {
                $validated['actif'] = $request->boolean('actif');
            }

            $formation->update($validated);

            return back()->with('success', 'Formation mise à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateFormation: ' . $e->getMessage());
            return back()->withErrors(['formation' => 'Impossible de mettre à jour la formation : ' . $e->getMessage()]);
        }
    }

    public function destroyFormation(VitrineFormation $formation): RedirectResponse
    {
        $formation->delete();
        return back()->with('success', 'Formation supprimée.');
    }

    // =========================================================================
    // 6. RECRUTEMENTS
    // =========================================================================

    public function storeRecrutement(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'metier' => 'required|string|max:150',
                'lieu' => 'required|string|max:255',
                'type_contrat' => 'required|in:cdi,cdd,stage,freelance,apprentissage',
                'date_limite' => 'nullable|date',
                'contact_email' => 'nullable|email|max:255',
                'actif' => 'nullable',
            ]);

            $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

            VitrineRecrutement::create($validated);

            return back()->with('success', 'Offre de recrutement publiée.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storeRecrutement: ' . $e->getMessage());
            return back()->withErrors(['recrutement' => 'Impossible de publier l\'offre : ' . $e->getMessage()]);
        }
    }

    public function updateRecrutement(Request $request, VitrineRecrutement $recrutement): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'metier' => 'required|string|max:150',
                'lieu' => 'required|string|max:255',
                'type_contrat' => 'required|in:cdi,cdd,stage,freelance,apprentissage',
                'date_limite' => 'nullable|date',
                'contact_email' => 'nullable|email|max:255',
                'actif' => 'nullable',
            ]);

            if ($request->has('actif')) {
                $validated['actif'] = $request->boolean('actif');
            }

            $recrutement->update($validated);

            return back()->with('success', 'Offre de recrutement mise à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateRecrutement: ' . $e->getMessage());
            return back()->withErrors(['recrutement' => 'Impossible de mettre à jour l\'offre : ' . $e->getMessage()]);
        }
    }

    public function destroyRecrutement(VitrineRecrutement $recrutement): RedirectResponse
    {
        $recrutement->delete();
        return back()->with('success', 'Offre supprimée.');
    }

    // =========================================================================
    // 7. POP-UPS
    // =========================================================================

    public function storePopup(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'contenu' => 'nullable|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'lien_cta' => 'nullable|string|max:255',
                'texte_cta' => 'nullable|string|max:100',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/popups', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);
            $validated['actif'] = $request->has('actif') ? $request->boolean('actif') : true;

            VitrinePopup::create($validated);

            return back()->with('success', 'Popup promotionnel créé.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur storePopup: ' . $e->getMessage());
            return back()->withErrors(['popup' => 'Impossible de créer le popup : ' . $e->getMessage()]);
        }
    }

    public function updatePopup(Request $request, VitrinePopup $popup): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'titre' => 'required|string|max:255',
                'contenu' => 'nullable|string',
                'image' => 'nullable|file|max:10240',
                'image_url' => 'nullable|string',
                'lien_cta' => 'nullable|string|max:255',
                'texte_cta' => 'nullable|string|max:100',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'actif' => 'nullable',
            ]);

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $path = $request->file('image')->store('vitrine/popups', 'public');
                $validated['image_url'] = '/storage/' . $path;
            }

            unset($validated['image']);
            if ($request->has('actif')) {
                $validated['actif'] = $request->boolean('actif');
            }

            $popup->update($validated);

            return back()->with('success', 'Popup mis à jour.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updatePopup: ' . $e->getMessage());
            return back()->withErrors(['popup' => 'Impossible de mettre à jour le popup : ' . $e->getMessage()]);
        }
    }

    public function destroyPopup(VitrinePopup $popup): RedirectResponse
    {
        $popup->delete();
        return back()->with('success', 'Popup supprimé.');
    }

    // =========================================================================
    // 8. SETTINGS
    // =========================================================================

    public function updateSettings(Request $request): RedirectResponse
    {
        try {
            $settings = $request->validate([
                'chiffres_cles_artisans' => 'nullable|string',
                'chiffres_cles_utilisateurs' => 'nullable|string',
                'chiffres_cles_missions' => 'nullable|string',
                'chiffres_cles_metiers' => 'nullable|string',
                'lien_facebook' => 'nullable|string|max:255',
                'lien_instagram' => 'nullable|string|max:255',
                'lien_linkedin' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'contact_email' => 'nullable|email|max:255',
                'presentation_mission' => 'nullable|string|max:5000',
            ]);

            foreach ($settings as $key => $value) {
                VitrineSetting::set($key, $value ?? '');
            }

            return back()->with('success', 'Paramètres de la vitrine enregistrés.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur updateSettings: ' . $e->getMessage());
            return back()->withErrors(['settings' => 'Impossible d\'enregistrer les paramètres : ' . $e->getMessage()]);
        }
    }
}
