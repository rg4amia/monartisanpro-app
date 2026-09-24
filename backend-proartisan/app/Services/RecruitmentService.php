<?php

namespace App\Services;

use App\Jobs\TranscribeRecruitmentVoiceNote;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Module de recrutement BTP & Métiers — mise en relation entre recruteurs
 * (admin, client, fournisseur) et artisans, distincte des annonces carrière
 * du site vitrine.
 */
class RecruitmentService
{
    public function __construct(
        private NotificationService $notifications,
        private GeminiService $gemini,
    ) {}

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

    /** Dossier des notes vocales de candidature sur le disque privé. */
    private const VOICE_NOTE_DIR = 'recruitment/voice-notes';

    public function applyToOffer(User $artisan, RecruitmentOffer $offer, ?UploadedFile $voiceNote = null, ?int $voiceNoteDuration = null): RecruitmentApplication
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

        $application = RecruitmentApplication::create([
            'offer_id' => $offer->id,
            'artisan_id' => $artisan->id,
            'matching_score' => $this->matchingScore($artisan, $offer),
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        if ($voiceNote) {
            $application->update([
                'voice_note_path' => $voiceNote->store(self::VOICE_NOTE_DIR, 'local'),
                'voice_note_duration' => $voiceNoteDuration,
                'voice_status' => 'pending',
            ]);

            TranscribeRecruitmentVoiceNote::dispatchAfterResponse($application->id);
        }

        $this->notifications->send(
            $offer->creator,
            'recruitment',
            'Nouvelle candidature',
            "{$artisan->name} a postulé à votre offre « {$offer->title} ».",
            ['recruitment_offer_id' => $offer->id, 'recruitment_application_id' => $application->id],
        );

        return $application;
    }

    /**
     * Transcrit la note vocale d'une candidature et applique la règle
     * anti-contournement : le numéro de l'artisan n'est jamais transmis au
     * recruteur, une note qui contient des coordonnées est donc supprimée
     * sans lui être transmise. Sans transcription possible, la note est
     * retenue plutôt que servie non vérifiée.
     */
    public function transcribeVoiceNote(int $applicationId): void
    {
        $application = RecruitmentApplication::with(['artisan', 'offer'])->find($applicationId);

        if (! $application || $application->voice_status !== 'pending' || ! $application->voice_note_path) {
            return;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        if (! $disk->exists($application->voice_note_path)) {
            $application->update(['voice_status' => 'failed']);

            return;
        }

        $result = $this->gemini->transcribeRecruitmentVoiceNote(
            base64_encode($disk->get($application->voice_note_path)),
            $disk->mimeType($application->voice_note_path) ?: 'audio/mp4',
            $application->artisan_id,
        );

        if ($result === null) {
            $application->update(['voice_status' => 'failed']);

            return;
        }

        if ($result['contains_contact_details']) {
            $disk->delete($application->voice_note_path);
            $application->update([
                'voice_note_path' => null,
                'voice_transcription' => null,
                'voice_status' => 'contact_detected',
            ]);

            $this->notifications->send(
                $application->artisan,
                'recruitment',
                'Note vocale non transmise',
                "Votre note vocale pour « {$application->offer->title} » contenait des coordonnées : elle n'a pas été transmise au recruteur. Votre candidature reste valable.",
                ['recruitment_application_id' => $application->id],
            );

            return;
        }

        $application->update([
            'voice_transcription' => $result['transcription'],
            'voice_status' => 'approved',
        ]);
    }

    private const APPLICATION_STATUSES = ['shortlisted', 'contacted', 'rejected', 'confirmed'];

    /**
     * Le recruteur (créateur de l'offre) ou un admin fait évoluer le statut
     * d'une candidature reçue.
     */
    public function updateApplicationStatus(User $actor, RecruitmentApplication $application, string $status): RecruitmentApplication
    {
        if (! in_array($status, self::APPLICATION_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Statut de candidature invalide.'],
            ]);
        }

        $offer = $application->offer;

        if ($offer->creator_id !== $actor->id && $actor->role !== 'admin') {
            throw ValidationException::withMessages([
                'application' => ["Cette candidature n'appartient pas à l'une de vos offres."],
            ]);
        }

        $application->update(['status' => $status]);

        $labels = [
            'shortlisted' => 'présélectionnée',
            'contacted' => 'marquée comme contactée',
            'rejected' => 'déclinée',
            'confirmed' => 'retenue',
        ];

        $this->notifications->send(
            $application->artisan,
            'recruitment',
            'Candidature mise à jour',
            "Votre candidature à « {$offer->title} » a été {$labels[$status]}.",
            ['recruitment_offer_id' => $offer->id, 'recruitment_application_id' => $application->id],
        );

        return $application;
    }

    /**
     * Le recruteur, dont le séquestre d'accès aux candidatures est déjà payé,
     * demande à l'artisan de le rappeler — le numéro de l'artisan n'est
     * jamais transmis au recruteur, seul le sien l'est à l'artisan.
     */
    public function requestCallback(User $client, RecruitmentApplication $application): void
    {
        $offer = $application->offer;

        if ($offer->creator_id !== $client->id && $client->role !== 'admin') {
            throw ValidationException::withMessages([
                'application' => ["Cette candidature n'appartient pas à l'une de vos offres."],
            ]);
        }

        if (! $offer->applicantsUnlocked()) {
            throw ValidationException::withMessages([
                'offer' => ['Payez le séquestre pour consulter et contacter les candidatures de cette offre.'],
            ]);
        }

        $this->notifications->send(
            $application->artisan,
            'recruitment',
            'Demande de rappel',
            "{$client->name} recrute pour « {$offer->title} » et souhaite que vous le rappeliez au {$client->phone}.",
            ['recruitment_offer_id' => $offer->id, 'recruitment_application_id' => $application->id],
        );
    }

    public function approve(RecruitmentOffer $offer): RecruitmentOffer
    {
        $offer->update(['status' => 'active']);

        $this->notifications->send(
            $offer->creator,
            'recruitment',
            'Offre publiée',
            "Votre offre « {$offer->title} » a été validée et est maintenant visible par les artisans.",
            ['recruitment_offer_id' => $offer->id],
        );

        return $offer;
    }

    public function reject(RecruitmentOffer $offer, ?string $reason = null): RecruitmentOffer
    {
        $offer->update([
            'status' => 'cancelled',
            'metadata' => array_merge($offer->metadata ?? [], ['moderation_note' => $reason]),
        ]);

        $this->notifications->send(
            $offer->creator,
            'recruitment',
            'Offre rejetée',
            $reason
                ? "Votre offre « {$offer->title} » a été rejetée : {$reason}"
                : "Votre offre « {$offer->title} » a été rejetée par l'équipe ProsArtisan.",
            ['recruitment_offer_id' => $offer->id],
        );

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
     * Active/désactive la publication d'offres par espace (client / fournisseur).
     *
     * @param  array<string, string|null>  $values  réglages déjà validés
     */
    public function updateSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('recruitment_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }
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
