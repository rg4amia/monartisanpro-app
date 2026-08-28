<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrineSetting;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitrineController extends Controller
{
    /**
     * Slides actifs triés par ordre.
     */
    public function slides(): JsonResponse
    {
        $slides = VitrineSlide::actif()->ordered()->get();

        return response()->json(['success' => true, 'data' => $slides]);
    }

    /**
     * Artisan du mois courant avec profil et photo.
     */
    public function artisanDuMois(): JsonResponse
    {
        $adm = VitrineArtisanDuMois::actif()
            ->with(['user:id,name,phone,role,score_prosartisan', 'user.artisanProfile.trade'])
            ->latest('mois')
            ->first();

        if (!$adm || !$adm->user) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $adm->id,
                'mois' => $adm->mois ? $adm->mois->format('Y-m') : now()->format('Y-m'),
                'texte_editorial' => $adm->texte_editorial,
                'photo_url' => $adm->photo_url, // Accesseur : override > KYC selfie
                'artisan' => [
                    'id' => $adm->user->id,
                    'name' => $adm->user->name,
                    'trade' => $adm->user->trade ?? 'Artisan Qualifié',
                    'score_prosartisan' => $adm->user->score_prosartisan,
                ],
            ],
        ]);
    }

    /**
     * Top artisans (score > 700, paginés).
     */
    public function artisansStars(Request $request): JsonResponse
    {
        $artisans = User::where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->where('score_prosartisan', '>=', 700)
            ->with(['artisanProfile.trade', 'commune'])
            ->orderByDesc('score_prosartisan')
            ->select('id', 'name', 'score_prosartisan', 'commune_id')
            ->paginate($request->input('per_page', 12));

        return response()->json(['success' => true, 'data' => $artisans]);
    }

    /**
     * Listing des artisans avec filtres.
     */
    public function artisans(Request $request): JsonResponse
    {
        $query = User::where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->with(['artisanProfile.trade', 'commune'])
            ->select('id', 'name', 'score_prosartisan', 'commune_id');

        if ($metier = $request->input('metier')) {
            $query->whereHas('artisanProfile.trade', function ($q) use ($metier) {
                $q->where('name', 'like', "%{$metier}%");
            });
        }
        if ($ville = $request->input('ville')) {
            $query->whereHas('commune', function ($q) use ($ville) {
                $q->where('nom', 'like', "%{$ville}%");
            });
        }
        if ($noteMin = $request->input('note_min')) {
            $query->where('score_prosartisan', '>=', (int) $noteMin);
        }

        $artisans = $query->orderByDesc('score_prosartisan')
                          ->paginate($request->input('per_page', 12));

        return response()->json(['success' => true, 'data' => $artisans]);
    }

    /**
     * Profil public d'un artisan.
     */
    public function artisanShow(int $id): JsonResponse
    {
        $artisan = User::where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->with(['artisanProfile.trade', 'commune'])
            ->select('id', 'name', 'score_prosartisan', 'commune_id', 'created_at')
            ->findOrFail($id);

        // Récupérer les évaluations publiques
        $evaluations = $artisan->evaluationsRecues()
            ->with('evaluateur:id,name')
            ->latest()
            ->take(10)
            ->get(['id', 'note', 'commentaire', 'fiabilite', 'integrite', 'qualite', 'reactivite', 'evaluateur_id', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'artisan' => $artisan,
                'evaluations' => $evaluations,
                'missions_completees' => $artisan->missionsArtisan()->where('status', 'completed')->count(),
            ],
        ]);
    }

    /**
     * Articles publiés, paginés.
     */
    public function articles(Request $request): JsonResponse
    {
        $query = VitrineArticle::publie()->latest('publie_at');

        if ($categorie = $request->input('categorie')) {
            $query->where('categorie', $categorie);
        }

        $articles = $query->paginate($request->input('per_page', 9));

        return response()->json(['success' => true, 'data' => $articles]);
    }

    /**
     * Détail d'un article par slug.
     */
    public function articleShow(string $slug): JsonResponse
    {
        $article = VitrineArticle::publie()
            ->where('slug', $slug)
            ->with('auteur:id,name')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $article]);
    }

    /**
     * Capsules vidéo actives.
     */
    public function videos(Request $request): JsonResponse
    {
        $query = VitrineVideo::actif()->ordered();

        if ($categorie = $request->input('categorie')) {
            $query->where('categorie', $categorie);
        }

        $videos = $query->paginate($request->input('per_page', 12));

        return response()->json(['success' => true, 'data' => $videos]);
    }

    /**
     * Formations actives.
     */
    public function formations(): JsonResponse
    {
        $formations = VitrineFormation::actif()
            ->aVenir()
            ->orderBy('date_debut')
            ->get();

        return response()->json(['success' => true, 'data' => $formations]);
    }

    /**
     * Offres de recrutement actives.
     */
    public function recrutements(): JsonResponse
    {
        $offres = VitrineRecrutement::actif()
            ->ouvert()
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $offres]);
    }

    /**
     * Pop-up actif en cours.
     */
    public function popup(): JsonResponse
    {
        $popup = VitrinePopup::actif()->enCours()->first();

        return response()->json(['success' => true, 'data' => $popup]);
    }

    /**
     * Paramètres publics (chiffres clés, réseaux sociaux, etc.).
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => VitrineSetting::allAsArray(),
        ]);
    }

    /**
     * Envoi formulaire de contact.
     */
    public function contact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:150',
            'email' => 'required|email|max:255',
            'sujet' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        // Stocker ou envoyer par email (pour l'instant, on log)
        \Log::channel('single')->info('Contact vitrine', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Votre message a été envoyé avec succès.',
        ]);
    }
}
