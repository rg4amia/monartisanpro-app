<?php

namespace App\Services\Admin;

use App\Models\ArtisanAvailability;
use App\Models\User;
use App\Services\ArtisanAvailabilityService;
use App\Services\ArtisanDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Onglet « Annuaire artisans » du backoffice (Chantier 15) : validation des
 * disponibilités déclarées, saisie directe, présence dans l'annuaire du site
 * vitrine. Toute action est auditée (Règle d'or 17).
 */
class ArtisanDirectoryAdminService
{
    public function __construct(
        private ArtisanDirectoryService $directory,
        private ArtisanAvailabilityService $availabilities,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panelData(Request $request): array
    {
        return [
            'directoryArtisans' => $this->list($request),
            'directoryStats' => $this->stats(),
            'directoryOptions' => [
                'statuses' => ArtisanAvailability::STATUS_LABELS,
                'days' => ArtisanAvailability::DAY_LABELS,
                'max_slots' => ArtisanAvailabilityService::MAX_SLOTS,
            ],
        ];
    }

    /**
     * Artisans, une page à la fois (Règle d'or 19) ; ceux dont une
     * disponibilité attend une décision passent en tête.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', 'artisan')
            ->whereNull('anonymized_at')
            ->with(['artisanProfile.trade', 'commune', 'publishedAvailability', 'pendingAvailability']);

        if ($search = trim((string) $request->query('directory_search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        match ((string) $request->query('directory_visibility', '')) {
            'visible' => $query->whereNull('directory_hidden_at'),
            'hidden' => $query->whereNotNull('directory_hidden_at'),
            default => null,
        };

        match ((string) $request->query('directory_availability', '')) {
            'pending' => $query->whereHas('pendingAvailability'),
            'published' => $query->whereHas('publishedAvailability'),
            'none' => $query->whereDoesntHave('publishedAvailability'),
            default => null,
        };

        /** @var LengthAwarePaginator $page */
        $page = $query
            ->withExists(['availabilities as has_pending_availability' => fn ($q) => $q->where('review_status', ArtisanAvailability::REVIEW_PENDING)])
            ->orderByDesc('has_pending_availability')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $page->getCollection()->transform(fn (User $artisan) => $this->present($artisan));

        return $page;
    }

    /**
     * Totaux indépendants de la page et des filtres.
     *
     * @return array{artisans: int, published: int, hidden: int, pending: int}
     */
    public function stats(): array
    {
        return [
            'artisans' => User::where('role', 'artisan')->whereNull('anonymized_at')->count(),
            'published' => $this->directory->visibleQuery()->count(),
            'hidden' => User::where('role', 'artisan')->whereNull('anonymized_at')->whereNotNull('directory_hidden_at')->count(),
            'pending' => ArtisanAvailability::where('review_status', ArtisanAvailability::REVIEW_PENDING)->count(),
        ];
    }

    public function approve(ArtisanAvailability $availability, User $admin): void
    {
        $before = $this->publishedSnapshot($availability->user);
        $this->availabilities->approve($availability, $admin);

        $this->audit->log('directory.availability.approved', $availability->user, [
            'disponibilite_id' => $availability->id,
            'avant' => $before,
            'apres' => $this->availabilities->present($availability->fresh()),
        ], $availability->user->name, $admin);
    }

    public function reject(ArtisanAvailability $availability, string $reason, User $admin): void
    {
        $this->availabilities->reject($availability, $reason, $admin);

        $this->audit->log('directory.availability.rejected', $availability->user, [
            'disponibilite_id' => $availability->id,
            'motif' => $reason,
            'proposee' => $this->availabilities->present($availability->fresh()),
        ], $availability->user->name, $admin);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function setAvailability(User $artisan, array $input, User $admin): void
    {
        abort_unless($artisan->role === 'artisan', 404);

        $before = $this->publishedSnapshot($artisan);
        $availability = $this->availabilities->setByAdmin($artisan, $input, $admin);

        $this->audit->log('directory.availability.admin_set', $artisan, [
            'avant' => $before,
            'apres' => $this->availabilities->present($availability),
        ], $artisan->name, $admin);
    }

    public function hide(User $artisan, string $reason, User $admin): void
    {
        $this->directory->hide($artisan, $reason, $admin);

        $this->audit->log('directory.artisan.hidden', $artisan, ['motif' => $reason], $artisan->name, $admin);
    }

    public function show(User $artisan, User $admin): void
    {
        $reason = $artisan->directory_hidden_reason;
        $this->directory->show($artisan);

        $this->audit->log('directory.artisan.shown', $artisan, ['motif_du_retrait' => $reason], $artisan->name, $admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $artisan): array
    {
        $blockers = $this->directory->blockers($artisan);

        return [
            'id' => $artisan->id,
            'name' => $artisan->name,
            'phone' => $artisan->phone,
            'trade' => $artisan->artisanProfile?->trade?->name,
            'city' => $artisan->commune?->name,
            'score_prosartisan' => (int) $artisan->score_prosartisan,
            'kyc_status' => $artisan->kyc_status,
            'account_status' => $artisan->account_status ?? 'actif',
            'hidden' => $artisan->directory_hidden_at !== null,
            'hidden_at' => $artisan->directory_hidden_at?->toIso8601String(),
            'hidden_reason' => $artisan->directory_hidden_reason,
            'listed' => $blockers === [],
            'blockers' => $blockers,
            'published' => $artisan->publishedAvailability ? $this->availabilities->present($artisan->publishedAvailability) : null,
            'pending' => $artisan->pendingAvailability ? $this->availabilities->present($artisan->pendingAvailability) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedSnapshot(User $artisan): ?array
    {
        $published = $artisan->publishedAvailability()->first();

        return $published ? $this->availabilities->present($published) : null;
    }
}
