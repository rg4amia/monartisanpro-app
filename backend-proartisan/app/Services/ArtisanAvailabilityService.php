<?php

namespace App\Services;

use App\Models\ArtisanAvailability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilité des artisans pour l'annuaire du site vitrine (Chantier 15).
 *
 * L'artisan déclare sa disponibilité (statut, jours et horaires, nuit) ; elle
 * reste en attente et la dernière version validée reste publiée jusqu'à la
 * décision d'un administrateur. Une saisie de l'administrateur est publiée
 * directement. Aucune version décidée n'est jamais réécrite.
 */
class ArtisanAvailabilityService
{
    /** Plages horaires déclarables, au plus. */
    public const MAX_SLOTS = 14;

    /** Horizon maximal d'une date de retour, en jours. */
    public const MAX_UNTIL_DAYS = 365;

    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(private NotificationService $notifications) {}

    /**
     * Contrôle et normalise une disponibilité déclarée.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: string, until_date: ?string, schedule_json: list<array{day: int, start: string, end: string}>, night_work: bool}
     *
     * @throws ValidationException
     */
    public function normalize(array $input): array
    {
        $errors = [];
        $status = (string) ($input['status'] ?? '');

        if (! array_key_exists($status, ArtisanAvailability::STATUS_LABELS)) {
            $errors['status'] = 'Choisissez un statut : disponible, occupé ou en congé.';
        }

        $until = null;
        if ($status !== ArtisanAvailability::STATUS_AVAILABLE && isset(ArtisanAvailability::STATUS_LABELS[$status])) {
            try {
                $until = filled($input['until_date'] ?? null) ? Carbon::parse((string) $input['until_date'])->startOfDay() : null;
            } catch (\Throwable) {
                $until = null;
            }
            if ($until === null) {
                $errors['until_date'] = 'Indiquez la date jusqu\'à laquelle vous êtes '.mb_strtolower(ArtisanAvailability::STATUS_LABELS[$status]).'.';
            } elseif ($until->lt(today())) {
                $errors['until_date'] = 'La date de retour est déjà passée.';
            } elseif ($until->gt(today()->addDays(self::MAX_UNTIL_DAYS))) {
                $errors['until_date'] = 'La date de retour ne peut pas dépasser un an.';
            }
        }

        $slots = [];
        $raw = $input['schedule'] ?? [];
        if (! is_array($raw) || count($raw) > self::MAX_SLOTS) {
            $errors['schedule'] = 'Au plus '.self::MAX_SLOTS.' plages horaires.';
            $raw = [];
        }
        foreach (array_values($raw) as $index => $slot) {
            $day = (int) ($slot['day'] ?? 0);
            $start = (string) ($slot['start'] ?? '');
            $end = (string) ($slot['end'] ?? '');
            if (! isset(ArtisanAvailability::DAY_LABELS[$day]) || ! preg_match(self::TIME, $start) || ! preg_match(self::TIME, $end)) {
                $errors["schedule.{$index}"] = 'Plage horaire invalide : choisissez un jour et des heures au format HH:MM.';

                continue;
            }
            if ($start >= $end) {
                $errors["schedule.{$index}"] = ArtisanAvailability::DAY_LABELS[$day].' : l\'heure de fin doit suivre l\'heure de début.';

                continue;
            }
            foreach ($slots as $other) {
                if ($other['day'] === $day && $start < $other['end'] && $other['start'] < $end) {
                    $errors["schedule.{$index}"] = ArtisanAvailability::DAY_LABELS[$day].' : deux plages horaires se chevauchent.';

                    continue 2;
                }
            }
            $slots[] = ['day' => $day, 'start' => $start, 'end' => $end];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        usort($slots, fn (array $a, array $b) => [$a['day'], $a['start']] <=> [$b['day'], $b['start']]);

        return [
            'status' => $status,
            'until_date' => $until?->toDateString(),
            'schedule_json' => $slots,
            'night_work' => (bool) ($input['night_work'] ?? false),
        ];
    }

    /**
     * Déclaration de l'artisan : en attente de validation. Une déclaration
     * encore en attente est remplacée par la nouvelle.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function submit(User $artisan, array $input): ArtisanAvailability
    {
        $values = $this->normalize($input);

        $published = $artisan->publishedAvailability()->first();
        if ($published !== null && $this->sameAs($published, $values)) {
            throw ValidationException::withMessages(['status' => 'Cette disponibilité est identique à celle déjà publiée.']);
        }

        return DB::transaction(function () use ($artisan, $values) {
            $this->supersedePending($artisan);

            return ArtisanAvailability::create($values + [
                'user_id' => $artisan->id,
                'review_status' => ArtisanAvailability::REVIEW_PENDING,
                'submitted_by' => $artisan->id,
            ]);
        });
    }

    /**
     * Publie une déclaration en attente.
     *
     * @throws ValidationException
     */
    public function approve(ArtisanAvailability $availability, User $admin): void
    {
        $this->ensurePending($availability);

        $availability->forceFill([
            'review_status' => ArtisanAvailability::REVIEW_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifications->notify($availability->user, 'annuaire.disponibilite_validee.artisan');
    }

    /**
     * Refuse une déclaration en attente ; la version publiée reste affichée.
     *
     * @throws ValidationException
     */
    public function reject(ArtisanAvailability $availability, string $reason, User $admin): void
    {
        $this->ensurePending($availability);

        $availability->forceFill([
            'review_status' => ArtisanAvailability::REVIEW_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifications->notify($availability->user, 'annuaire.disponibilite_refusee.artisan', ['motif' => $reason]);
    }

    /**
     * Saisie ou correction par un administrateur : publiée directement, elle
     * remplace toute déclaration en attente.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function setByAdmin(User $artisan, array $input, User $admin): ArtisanAvailability
    {
        $values = $this->normalize($input);

        $availability = DB::transaction(function () use ($artisan, $values, $admin) {
            $this->supersedePending($artisan);

            return ArtisanAvailability::create($values + [
                'user_id' => $artisan->id,
                'review_status' => ArtisanAvailability::REVIEW_APPROVED,
                'submitted_by' => $admin->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });

        $this->notifications->notify($artisan, 'annuaire.disponibilite_validee.artisan');

        return $availability;
    }

    /**
     * Vue de l'artisan : version publiée, déclaration en attente, dernier refus.
     *
     * @return array{published: ?array<string, mixed>, pending: ?array<string, mixed>, last_rejected: ?array<string, mixed>}
     */
    public function overviewFor(User $artisan): array
    {
        $published = $artisan->publishedAvailability()->first();
        $pending = $artisan->pendingAvailability()->first();
        $rejected = $artisan->availabilities()
            ->where('review_status', ArtisanAvailability::REVIEW_REJECTED)
            ->when($published, fn ($q) => $q->where('id', '>', $published->id))
            ->latest('id')
            ->first();

        return [
            'published' => $published ? $this->present($published) : null,
            'pending' => $pending ? $this->present($pending) : null,
            // Un refus n'est utile que s'il est postérieur à la version publiée.
            'last_rejected' => $rejected && ! $pending ? $this->present($rejected) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ArtisanAvailability $availability): array
    {
        $effective = $availability->effectiveStatus();

        return [
            'id' => $availability->id,
            'status' => $availability->status,
            'status_label' => ArtisanAvailability::STATUS_LABELS[$availability->status] ?? $availability->status,
            'effective_status' => $effective,
            'effective_label' => $this->label($availability, $effective),
            'until_date' => $availability->until_date?->toDateString(),
            'schedule' => $availability->schedule_json ?? [],
            'schedule_summary' => $this->scheduleSummary($availability->schedule_json ?? []),
            'night_work' => $availability->night_work,
            'review_status' => $availability->review_status,
            'review_label' => ArtisanAvailability::REVIEW_LABELS[$availability->review_status] ?? $availability->review_status,
            'rejection_reason' => $availability->rejection_reason,
            'submitted_at' => $availability->created_at?->toIso8601String(),
            'reviewed_at' => $availability->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * « Lundi au vendredi 08:00–17:00 · Samedi 08:00–12:00 » : les jours
     * consécutifs aux mêmes horaires sont regroupés.
     *
     * @param  list<array{day: int, start: string, end: string}>  $slots
     */
    public function scheduleSummary(array $slots): ?string
    {
        if ($slots === []) {
            return null;
        }

        $byDay = [];
        foreach ($slots as $slot) {
            $byDay[(int) $slot['day']][] = $slot['start'].'–'.$slot['end'];
        }
        ksort($byDay);

        $groups = [];
        foreach ($byDay as $day => $ranges) {
            $hours = implode(', ', $ranges);
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['hours'] === $hours && $groups[$last]['to'] === $day - 1) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'hours' => $hours];
            }
        }

        return implode(' · ', array_map(function (array $group) {
            $days = ArtisanAvailability::DAY_LABELS[$group['from']];
            if ($group['to'] !== $group['from']) {
                $days .= ($group['to'] - $group['from'] === 1 ? ' et ' : ' au ').mb_strtolower(ArtisanAvailability::DAY_LABELS[$group['to']]);
            }

            return $days.' '.$group['hours'];
        }, $groups));
    }

    private function label(ArtisanAvailability $availability, string $effective): string
    {
        $label = ArtisanAvailability::STATUS_LABELS[$effective] ?? $effective;

        if ($effective !== ArtisanAvailability::STATUS_AVAILABLE && $availability->until_date !== null) {
            $label .= ' jusqu\'au '.$availability->until_date->format('d/m/Y');
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function sameAs(ArtisanAvailability $availability, array $values): bool
    {
        return $availability->status === $values['status']
            && $availability->until_date?->toDateString() === $values['until_date']
            && ($availability->schedule_json ?? []) === $values['schedule_json']
            && $availability->night_work === $values['night_work'];
    }

    private function supersedePending(User $artisan): void
    {
        $artisan->availabilities()
            ->where('review_status', ArtisanAvailability::REVIEW_PENDING)
            ->update(['review_status' => ArtisanAvailability::REVIEW_SUPERSEDED, 'updated_at' => now()]);
    }

    /**
     * @throws ValidationException
     */
    private function ensurePending(ArtisanAvailability $availability): void
    {
        if ($availability->review_status !== ArtisanAvailability::REVIEW_PENDING) {
            throw ValidationException::withMessages(['availability' => 'Cette disponibilité a déjà été traitée ou remplacée par une déclaration plus récente.']);
        }
    }
}
