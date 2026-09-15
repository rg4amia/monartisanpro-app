<?php

namespace App\Services;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Module de recrutement BTP & Métiers — mise en relation entre recruteurs
 * (admin, client, fournisseur) et artisans, distincte des annonces carrière
 * du site vitrine.
 */
class RecruitmentService
{
    private const CREATOR_TYPES = ['admin', 'client', 'fournisseur'];

    /**
     * @param  array{trade_id:int, title:string, description:string, mission_type:string,
     *     commune:string, sous_quartier?:?string, lat?:?float, lng?:?float, date_debut?:?string,
     *     daily_rate_min?:?int, daily_rate_max?:?int, openings_count?:int, deadline_at?:?string}  $data
     */
    public function createOffer(User $creator, array $data): RecruitmentOffer
    {
        if (! in_array($creator->role, self::CREATOR_TYPES, true)) {
            throw ValidationException::withMessages([
                'creator' => ['Seuls les comptes admin, client ou fournisseur peuvent publier une offre de recrutement.'],
            ]);
        }

        if ($creator->role !== 'admin' && ! $this->postingEnabledFor($creator->role)) {
            throw ValidationException::withMessages([
                'creator' => ["La publication d'offres de recrutement est désactivée pour votre espace."],
            ]);
        }

        if (! empty($data['date_debut']) && ! empty($data['deadline_at'])
            && strtotime($data['date_debut']) > strtotime($data['deadline_at'])) {
            throw ValidationException::withMessages([
                'deadline_at' => ['La date de fin doit être postérieure ou égale à la date de début.'],
            ]);
        }

        $status = $creator->role === 'admin' || $creator->kyc_status === 'actif'
            ? 'active'
            : 'pending_review';

        $offer = RecruitmentOffer::create([
            'creator_id' => $creator->id,
            'creator_type' => $creator->role,
            'trade_id' => $data['trade_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'mission_type' => $data['mission_type'],
            'commune' => $data['commune'],
            'sous_quartier' => $data['sous_quartier'] ?? null,
            'date_debut' => $data['date_debut'] ?? null,
            'daily_rate_min' => $data['daily_rate_min'] ?? null,
            'daily_rate_max' => $data['daily_rate_max'] ?? null,
            'openings_count' => $data['openings_count'] ?? 1,
            'deadline_at' => $data['deadline_at'] ?? null,
            'status' => $status,
        ]);

        if (isset($data['lat'], $data['lng'])) {
            DB::statement(
                'UPDATE recruitment_offers SET position = POINT(?, ?) WHERE id = ?',
                [$data['lng'], $data['lat'], $offer->id],
            );
        }

        return $offer->fresh();
    }

    public function applyToOffer(User $artisan, RecruitmentOffer $offer): RecruitmentApplication
    {
        if ($artisan->role !== 'artisan') {
            throw ValidationException::withMessages([
                'artisan' => ['Seul un compte artisan peut postuler à une offre de recrutement.'],
            ]);
        }

        if ($artisan->kyc_status !== 'actif') {
            throw ValidationException::withMessages([
                'artisan' => ['Votre KYC doit être validé pour postuler à une offre.'],
            ]);
        }

        if (! $offer->isActive()) {
            throw ValidationException::withMessages([
                'offer' => ["Cette offre n'accepte plus de candidature."],
            ]);
        }

        if (RecruitmentApplication::where('offer_id', $offer->id)->where('artisan_id', $artisan->id)->exists()) {
            throw ValidationException::withMessages([
                'offer' => ['Vous avez déjà postulé à cette offre.'],
            ]);
        }

        return RecruitmentApplication::create([
            'offer_id' => $offer->id,
            'artisan_id' => $artisan->id,
            'matching_score' => $this->matchingScore($artisan, $offer),
            'status' => 'submitted',
            'applied_at' => now(),
        ]);
    }

    public function approve(RecruitmentOffer $offer): RecruitmentOffer
    {
        $offer->update(['status' => 'active']);

        return $offer;
    }

    public function reject(RecruitmentOffer $offer, ?string $reason = null): RecruitmentOffer
    {
        $offer->update([
            'status' => 'cancelled',
            'metadata' => array_merge($offer->metadata ?? [], ['moderation_note' => $reason]),
        ]);

        return $offer;
    }

    /**
     * Clôture automatique des offres actives dont la date limite est dépassée.
     * Appelé par la commande planifiée `recruitment:expire-offers`.
     */
    public function expireOverdueOffers(): int
    {
        return RecruitmentOffer::where('status', 'active')
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    public function postingEnabledFor(string $role): bool
    {
        $key = $role === 'client' ? 'client_posting_enabled' : 'fournisseur_posting_enabled';

        return DB::table('recruitment_settings')->where('key', $key)->value('value') !== '0';
    }

    /**
     * Score de matching déterministe (0-100), simplifié pour la Phase 1 :
     * proximité spatiale (50%) et fiabilité via le Score ProsArtisan (50%).
     * La composante outillage/disponibilité de la spécification d'origine est
     * différée : aucune donnée fiable de disponibilité temps réel n'existe
     * encore sur `artisan_profiles` (cf. règle d'or 29 — jamais de signal
     * fictif).
     */
    private function matchingScore(User $artisan, RecruitmentOffer $offer): float
    {
        $reliability = min(100, max(0, ($artisan->score_prosartisan ?? 0) / 1000 * 100));

        $distanceScore = $this->distanceScore($artisan, $offer);

        if ($distanceScore === null) {
            return round($reliability, 2);
        }

        return round(($distanceScore * 0.5) + ($reliability * 0.5), 2);
    }

    private function distanceScore(User $artisan, RecruitmentOffer $offer): ?float
    {
        if (config('database.default') === 'sqlite') {
            return null;
        }

        $row = DB::selectOne('
            SELECT ST_Distance_Sphere(u.position, o.position) AS distance_metres
            FROM users u, recruitment_offers o
            WHERE u.id = ? AND o.id = ? AND u.position IS NOT NULL AND o.position IS NOT NULL
        ', [$artisan->id, $offer->id]);

        if (! $row || $row->distance_metres === null) {
            return null;
        }

        $km = $row->distance_metres / 1000;

        if ($km <= 2) {
            return 100.0;
        }

        if ($km <= 10) {
            return 100 - (($km - 2) / (10 - 2)) * 60;
        }

        if ($km <= 15) {
            return 40 - (($km - 10) / (15 - 10)) * 40;
        }

        return 0.0;
    }
}
