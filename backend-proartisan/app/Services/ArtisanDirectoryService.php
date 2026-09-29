<?php

namespace App\Services;

use App\Models\ArtisanAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Annuaire des artisans du site vitrine (Chantier 15) : qui y figure, ce qui
 * en est montré, et le retrait ou le retour d'une fiche par un administrateur.
 * Seules des informations professionnelles sont exposées : ni téléphone, ni
 * pièce KYC, ni position (Règle d'or 6).
 */
class ArtisanDirectoryService
{
    public const MAX_PER_PAGE = 48;

    public function __construct(
        private ArtisanAvailabilityService $availabilities,
        private NotificationService $notifications,
    ) {}

    /**
     * Artisans publiables : KYC actif, compte actif, non anonymisé, non
     * supprimé et non retiré de l'annuaire.
     *
     * @return Builder<User>
     */
    public function visibleQuery(): Builder
    {
        return User::query()
            ->where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->where(fn (Builder $q) => $q->whereNull('account_status')->orWhere('account_status', 'actif'))
            ->whereNull('anonymized_at')
            ->whereNull('directory_hidden_at');
    }

    /**
     * Recherche publique : métier, commune, score minimum, disponibles
     * maintenant.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->visibleQuery()->with(['artisanProfile.trade', 'commune', 'publishedAvailability']);

        if ($metier = trim((string) ($filters['metier'] ?? ''))) {
            $query->whereHas('artisanProfile.trade', fn ($q) => $q->where('name', 'like', "%{$metier}%"));
        }
        if ($ville = trim((string) ($filters['ville'] ?? ''))) {
            $query->whereHas('commune', fn ($q) => $q->where('name', 'like', "%{$ville}%"));
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where('name', 'like', "%{$search}%");
        }
        if (($noteMin = (int) ($filters['note_min'] ?? 0)) > 0) {
            $query->where('score_prosartisan', '>=', $noteMin);
        }
        if (filter_var($filters['disponible'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('publishedAvailability', fn ($q) => $q->where(fn ($q) => $q
                ->where('status', ArtisanAvailability::STATUS_AVAILABLE)
                ->orWhere('until_date', '<', today()->toDateString())));
        }

        return $this->paginate($query->orderByDesc('score_prosartisan')->orderBy('id'), $perPage);
    }

    /**
     * Artisans les mieux notés (score ≥ 700).
     */
    public function stars(int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->visibleQuery()
            ->with(['artisanProfile.trade', 'commune', 'publishedAvailability'])
            ->where('score_prosartisan', '>=', (int) config('prosartisan.score_prosartisan.golden_marker_threshold', 700))
            ->orderByDesc('score_prosartisan')
            ->orderBy('id');

        return $this->paginate($query, $perPage);
    }

    /**
     * Fiche publique : 404 pour un artisan absent de l'annuaire.
     *
     * @return array<string, mixed>
     */
    public function profile(int $id): array
    {
        /** @var User $artisan */
        $artisan = $this->visibleQuery()
            ->with(['artisanProfile.trade', 'commune', 'publishedAvailability'])
            ->findOrFail($id);

        $evaluations = $artisan->evaluationsRecues()
            ->with('evaluateur:id,name')
            ->latest()
            ->take(10)
            ->get(['id', 'note', 'commentaire', 'fiabilite', 'integrite', 'qualite', 'reactivite', 'evaluateur_id', 'created_at']);

        return [
            'artisan' => $this->present($artisan) + ['created_at' => $artisan->created_at?->toIso8601String()],
            'evaluations' => $evaluations,
            'missions_completees' => $artisan->missionsArtisan()->where('status', 'completed')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $artisan): array
    {
        $availability = $artisan->publishedAvailability;

        return [
            'id' => $artisan->id,
            'name' => $artisan->name,
            'trade' => $artisan->artisanProfile?->trade?->name,
            'city' => $artisan->commune?->name,
            'score_prosartisan' => (int) $artisan->score_prosartisan,
            'photo_url' => $artisan->artisanProfile?->photo_url,
            'is_golden_marker' => $artisan->isGoldenMarker(),
            'availability' => $availability ? $this->publicAvailability($availability) : null,
        ];
    }

    /**
     * Retire la fiche de l'annuaire ; le compte et les missions ne changent pas.
     *
     * @throws ValidationException
     */
    public function hide(User $artisan, string $reason, User $admin): void
    {
        $this->ensureArtisan($artisan);
        if ($artisan->directory_hidden_at !== null) {
            throw ValidationException::withMessages(['directory' => 'Cette fiche est déjà retirée de l\'annuaire.']);
        }

        $artisan->forceFill([
            'directory_hidden_at' => now(),
            'directory_hidden_by' => $admin->id,
            'directory_hidden_reason' => $reason,
        ])->save();

        $this->notifications->notify($artisan, 'annuaire.retire.artisan', ['motif' => $reason]);
    }

    /**
     * @throws ValidationException
     */
    public function show(User $artisan): void
    {
        $this->ensureArtisan($artisan);
        if ($artisan->directory_hidden_at === null) {
            throw ValidationException::withMessages(['directory' => 'Cette fiche est déjà visible dans l\'annuaire.']);
        }

        $artisan->forceFill([
            'directory_hidden_at' => null,
            'directory_hidden_by' => null,
            'directory_hidden_reason' => null,
        ])->save();

        $this->notifications->notify($artisan, 'annuaire.reactive.artisan');
    }

    /**
     * Motifs pour lesquels un artisan visible dans l'annuaire n'y figure
     * pourtant pas (KYC, compte), pour l'expliquer au backoffice.
     *
     * @return list<string>
     */
    public function blockers(User $artisan): array
    {
        return array_values(array_filter([
            $artisan->kyc_status !== 'actif' ? 'KYC non validé' : null,
            ($artisan->account_status ?? 'actif') !== 'actif' ? 'Compte suspendu' : null,
            $artisan->anonymized_at !== null ? 'Compte anonymisé' : null,
            $artisan->directory_hidden_at !== null ? 'Retiré de l\'annuaire' : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function publicAvailability(ArtisanAvailability $availability): array
    {
        $presented = $this->availabilities->present($availability);

        return [
            'status' => $presented['effective_status'],
            'label' => $presented['effective_label'],
            'until_date' => $presented['effective_status'] === ArtisanAvailability::STATUS_AVAILABLE ? null : $presented['until_date'],
            'schedule' => $presented['schedule'],
            'schedule_summary' => $presented['schedule_summary'],
            'night_work' => $presented['night_work'],
        ];
    }

    private function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator $page */
        $page = $query->paginate(max(1, min(self::MAX_PER_PAGE, $perPage)))->withQueryString();
        $page->getCollection()->transform(fn (User $artisan) => $this->present($artisan));

        return $page;
    }

    /**
     * @throws ValidationException
     */
    private function ensureArtisan(User $user): void
    {
        if ($user->role !== 'artisan') {
            throw ValidationException::withMessages(['directory' => 'Seuls les artisans figurent dans l\'annuaire.']);
        }
    }
}
