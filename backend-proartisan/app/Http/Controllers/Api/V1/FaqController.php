<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * FAQ « Aide et support » de l'app mobile, filtrée par rôle
     * (client, artisan, livreur, fournisseur).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'nullable|string|in:client,artisan,livreur,fournisseur',
        ]);

        $faqs = Faq::actif()
            ->when($validated['role'] ?? null, fn ($q, $role) => $q->pourRole($role))
            ->ordered()
            ->get(['id', 'question', 'reponse', 'categorie', 'ordre']);

        return response()->json(['success' => true, 'data' => $faqs]);
    }
}
