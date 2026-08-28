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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'sous_titre' => 'nullable|string',
            'image' => 'required_without:image_url|image|max:5120', // 5MB max
            'image_url' => 'nullable|string',
            'cta_texte' => 'nullable|string|max:100',
            'cta_lien' => 'nullable|string|max:255',
            'ordre' => 'required|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/slides', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        VitrineSlide::create($validated);

        return back()->with('success', 'Slide créé avec succès.');
    }

    public function updateSlide(Request $request, VitrineSlide $slide): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'sous_titre' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'cta_texte' => 'nullable|string|max:100',
            'cta_lien' => 'nullable|string|max:255',
            'ordre' => 'required|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/slides', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $slide->update($validated);

        return back()->with('success', 'Slide mis à jour.');
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
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'mois' => 'required|date',
            'photo' => 'nullable|image|max:5120',
            'photo_override_url' => 'nullable|string',
            'texte_editorial' => 'nullable|string',
            'actif' => 'required|boolean',
        ]);

        // Formater le mois au format Y-m-01
        $validated['mois'] = \Carbon\Carbon::parse($validated['mois'])->startOfMonth()->toDateString();

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('vitrine/artisans', 'public');
            $validated['photo_override_url'] = '/storage/' . $path;
        }

        // Mettre à jour si le mois existe déjà, ou créer
        VitrineArtisanDuMois::updateOrCreate(
            ['mois' => $validated['mois']],
            $validated
        );

        return back()->with('success', 'Artisan du mois configuré.');
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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'categorie' => 'required|in:actualite,evenement,temoignage,partenariat',
            'publie' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/articles', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $validated['auteur_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['titre']) . '-' . Str::random(5);

        if ($validated['publie']) {
            $validated['publie_at'] = now();
        }

        VitrineArticle::create($validated);

        return back()->with('success', 'Article créé avec succès.');
    }

    public function updateArticle(Request $request, VitrineArticle $article): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'categorie' => 'required|in:actualite,evenement,temoignage,partenariat',
            'publie' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/articles', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        if ($validated['publie'] && !$article->publie) {
            $validated['publie_at'] = now();
        } elseif (!$validated['publie']) {
            $validated['publie_at'] = null;
        }

        $article->update($validated);

        return back()->with('success', 'Article mis à jour.');
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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|string',
            'thumbnail' => 'nullable|image|max:5120',
            'thumbnail_url' => 'nullable|string',
            'categorie' => 'required|in:capsule,formation,temoignage,evenement',
            'ordre' => 'required|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('vitrine/videos', 'public');
            $validated['thumbnail_url'] = '/storage/' . $path;
        }

        VitrineVideo::create($validated);

        return back()->with('success', 'Vidéo ajoutée.');
    }

    public function updateVideo(Request $request, VitrineVideo $video): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|string',
            'thumbnail' => 'nullable|image|max:5120',
            'thumbnail_url' => 'nullable|string',
            'categorie' => 'required|in:capsule,formation,temoignage,evenement',
            'ordre' => 'required|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('vitrine/videos', 'public');
            $validated['thumbnail_url'] = '/storage/' . $path;
        }

        $video->update($validated);

        return back()->with('success', 'Vidéo mise à jour.');
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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date',
            'lieu' => 'required|string|max:255',
            'formateur' => 'nullable|string|max:255',
            'places_total' => 'nullable|integer|min:0',
            'tarif' => 'required|integer|min:0',
            'lien_inscription' => 'nullable|string|max:255',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/formations', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $validated['places_restantes'] = $validated['places_total'];

        VitrineFormation::create($validated);

        return back()->with('success', 'Formation créée.');
    }

    public function updateFormation(Request $request, VitrineFormation $formation): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date',
            'lieu' => 'required|string|max:255',
            'formateur' => 'nullable|string|max:255',
            'places_total' => 'nullable|integer|min:0',
            'tarif' => 'required|integer|min:0',
            'lien_inscription' => 'nullable|string|max:255',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/formations', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        // Ajuster places restantes
        if (isset($validated['places_total'])) {
            $difference = $validated['places_total'] - ($formation->places_total ?? 0);
            $validated['places_restantes'] = max(0, ($formation->places_restantes ?? 0) + $difference);
        }

        $formation->update($validated);

        return back()->with('success', 'Formation mise à jour.');
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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'metier' => 'required|string|max:150',
            'lieu' => 'required|string|max:255',
            'type_contrat' => 'required|in:cdi,cdd,stage,freelance,apprentissage',
            'date_limite' => 'nullable|date',
            'contact_email' => 'nullable|email|max:255',
            'actif' => 'required|boolean',
        ]);

        VitrineRecrutement::create($validated);

        return back()->with('success', 'Offre de recrutement publiée.');
    }

    public function updateRecrutement(Request $request, VitrineRecrutement $recrutement): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'metier' => 'required|string|max:150',
            'lieu' => 'required|string|max:255',
            'type_contrat' => 'required|in:cdi,cdd,stage,freelance,apprentissage',
            'date_limite' => 'nullable|date',
            'contact_email' => 'nullable|email|max:255',
            'actif' => 'required|boolean',
        ]);

        $recrutement->update($validated);

        return back()->with('success', 'Offre de recrutement mise à jour.');
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
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'lien_cta' => 'nullable|string|max:255',
            'texte_cta' => 'nullable|string|max:100',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/popups', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        VitrinePopup::create($validated);

        return back()->with('success', 'Popup promotionnel créé.');
    }

    public function updatePopup(Request $request, VitrinePopup $popup): RedirectResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'image_url' => 'nullable|string',
            'lien_cta' => 'nullable|string|max:255',
            'texte_cta' => 'nullable|string|max:100',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'actif' => 'required|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('vitrine/popups', 'public');
            $validated['image_url'] = '/storage/' . $path;
        }

        $popup->update($validated);

        return back()->with('success', 'Popup mis à jour.');
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
        $settings = $request->validate([
            'chiffres_cles_artisans' => 'required|string',
            'chiffres_cles_utilisateurs' => 'required|string',
            'chiffres_cles_missions' => 'required|string',
            'chiffres_cles_metiers' => 'required|string',
            'lien_facebook' => 'nullable|string|max:255',
            'lien_instagram' => 'nullable|string|max:255',
            'lien_linkedin' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'presentation_mission' => 'required|string|max:5000',
        ]);

        foreach ($settings as $key => $value) {
            VitrineSetting::set($key, $value ?? '');
        }

        return back()->with('success', 'Paramètres de la vitrine enregistrés.');
    }
}
