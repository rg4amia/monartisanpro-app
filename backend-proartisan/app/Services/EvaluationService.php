<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\JCodeItem;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Évaluations des acteurs d'une mission terminée ou d'une commande livrée
 * (Règle d'or 10) : qui peut noter qui, enregistrement, et lecture sans
 * exposer le compte de l'autre partie.
 */
class EvaluationService
{
    private const DUPLICATE_MISSION = 'Vous avez déjà évalué cette personne pour cette mission.';

    private const DUPLICATE_ORDER = 'Vous avez déjà évalué cette personne pour cette commande.';

    public function __construct(private ScoreService $scores) {}

    /**
     * Enregistre l'évaluation et recalcule le score de l'évalué.
     *
     * @param  array<string, mixed>  $data  Données validées par CreateEvaluationRequest.
     * @return array{evaluation: Evaluation, score: int|null}
     */
    public function create(User $author, array $data): array
    {
        $evalue = User::findOrFail($data['evalue_id']);

        if ($evalue->id === $author->id) {
            $this->refuse('Vous ne pouvez pas vous auto-évaluer.');
        }

        $mission = ! empty($data['mission_id']) ? Mission::findOrFail($data['mission_id']) : null;
        $order = $mission === null && ! empty($data['order_id']) ? Order::findOrFail($data['order_id']) : null;

        if ($mission !== null) {
            $this->assertMissionEvaluable($mission, $author, $evalue);
        } elseif ($order !== null) {
            $this->assertOrderEvaluable($order, $author, $evalue);
        } else {
            $this->refuse('Une mission ou une commande doit être spécifiée.');
        }

        $note = (int) $data['note'];

        try {
            $evaluation = Evaluation::create([
                'evaluateur_id' => $author->id,
                'evalue_id' => $evalue->id,
                'mission_id' => $mission?->id,
                'order_id' => $order?->id,
                'note' => $note,
                'commentaire' => $data['commentaire'] ?? null,
                // Un critère non renseigné reprend la note globale.
                'fiabilite' => (int) ($data['fiabilite'] ?? $note) ?: $note,
                'integrite' => (int) ($data['integrite'] ?? $note) ?: $note,
                'qualite' => (int) ($data['qualite'] ?? $note) ?: $note,
                'reactivite' => (int) ($data['reactivite'] ?? $note) ?: $note,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés : la base tranche (index unique).
            $this->refuse($mission !== null ? self::DUPLICATE_MISSION : self::DUPLICATE_ORDER);
        }

        return ['evaluation' => $evaluation, 'score' => $this->recalculateScore($evalue, $evaluation)];
    }

    private function assertMissionEvaluable(Mission $mission, User $author, User $evalue): void
    {
        if ($mission->client_id !== $author->id) {
            throw new AccessDeniedHttpException('Seul le client ayant demandé cette mission peut évaluer.');
        }

        if ((string) $mission->status !== 'completed') {
            $this->refuse('La mission doit être terminée pour pouvoir évaluer.');
        }

        // L'évalué est l'artisan de la mission ou un fournisseur qui l'a servie.
        // Un livreur s'évalue depuis sa commande : rien ne relie une commande à
        // une mission, donc aucun livreur n'est « associé » à une mission.
        $associated = $evalue->id === $mission->artisan_id
            || ($evalue->isFournisseur() && $this->supplierIdsOf($mission)->contains($evalue->id));

        if (! $associated) {
            $this->refuse($evalue->isLivreur()
                ? 'Un livreur s\'évalue depuis la commande qu\'il a livrée.'
                : 'L\'utilisateur évalué n\'est pas associé à cette mission.');
        }

        if (Evaluation::where('mission_id', $mission->id)->where('evaluateur_id', $author->id)->where('evalue_id', $evalue->id)->exists()) {
            $this->refuse(self::DUPLICATE_MISSION);
        }
    }

    private function assertOrderEvaluable(Order $order, User $author, User $evalue): void
    {
        if ($order->client_id !== $author->id) {
            throw new AccessDeniedHttpException('Seul le client ayant passé la commande peut évaluer.');
        }

        if ($order->status !== 'delivered') {
            $this->refuse('La commande doit être livrée pour pouvoir évaluer.');
        }

        if (! in_array($evalue->id, [$order->supplier_id, $order->driver_id], true)) {
            $this->refuse('L\'utilisateur évalué n\'est pas associé à cette commande.');
        }

        if (Evaluation::where('order_id', $order->id)->where('evaluateur_id', $author->id)->where('evalue_id', $evalue->id)->exists()) {
            $this->refuse(self::DUPLICATE_ORDER);
        }
    }

    /**
     * Le recalcul ne doit jamais faire échouer l'évaluation déjà enregistrée.
     */
    private function recalculateScore(User $evalue, Evaluation $evaluation): ?int
    {
        try {
            if ($evalue->isArtisan()) {
                return $this->scores->recalculate($evalue, $evaluation);
            }
            if ($evalue->isFournisseur() || $evalue->isLivreur()) {
                return $this->scores->recalculateLogistic($evalue, $evaluation);
            }
        } catch (\Throwable $e) {
            Log::warning('Recalcul du score après évaluation : échec', ['evaluation_id' => $evaluation->id, 'message' => $e->getMessage()]);

            return (int) $evalue->score_prosartisan;
        }

        return null;
    }

    // ── Acteurs à évaluer ────────────────────────────────────────────────────

    public function canSeeMission(Mission $mission, User $user): bool
    {
        return $user->role === 'admin' || in_array($user->id, [$mission->client_id, $mission->artisan_id], true);
    }

    public function canSeeOrder(Order $order, User $user): bool
    {
        return $user->role === 'admin' || in_array($user->id, [$order->client_id, $order->supplier_id, $order->driver_id], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function actorsForMission(Mission $mission, User $viewer): array
    {
        $actors = [];

        $artisan = $mission->artisan_id ? User::with('artisanProfile.trade')->find($mission->artisan_id) : null;
        if ($artisan) {
            $actors[] = $this->actor(
                $artisan,
                $artisan->name,
                'artisan',
                'Artisan',
                $artisan->artisanProfile?->trade?->name ?? 'Artisan qualifié',
                Evaluation::where('mission_id', $mission->id)->where('evaluateur_id', $viewer->id)->where('evalue_id', $artisan->id)->first(),
                true,
            );
        }

        foreach (User::with('fournisseurAgree')->whereIn('id', $this->supplierIdsOf($mission))->get() as $supplier) {
            $actors[] = $this->actor(
                $supplier,
                $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                'fournisseur',
                'Quincaillerie & Fournisseur',
                'Fournisseur agréé de matériaux',
                Evaluation::where('mission_id', $mission->id)->where('evaluateur_id', $viewer->id)->where('evalue_id', $supplier->id)->first(),
                true,
            );
        }

        // Pas de livreur : rien ne relie une commande à une mission en base.
        // Le livreur s'évalue depuis la commande (`actorsForOrder`).

        return [
            'mission_id' => $mission->id,
            'status' => (string) $mission->status,
            'is_completed' => (string) $mission->status === 'completed',
            'actors' => $actors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actorsForOrder(Order $order, User $viewer): array
    {
        $actors = [];

        $supplier = $order->supplier_id ? User::with('fournisseurAgree')->find($order->supplier_id) : null;
        if ($supplier) {
            $actors[] = $this->actor(
                $supplier,
                $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                'fournisseur',
                'Fournisseur / Boutique',
                'Quincaillerie agréée',
                Evaluation::where('order_id', $order->id)->where('evaluateur_id', $viewer->id)->where('evalue_id', $supplier->id)->first(),
                false,
            );
        }

        $driver = $order->driver_id ? User::find($order->driver_id) : null;
        if ($driver) {
            $actors[] = $this->actor(
                $driver,
                $driver->name,
                'livreur',
                'Livreur Express',
                'Transport & logistique',
                Evaluation::where('order_id', $order->id)->where('evaluateur_id', $viewer->id)->where('evalue_id', $driver->id)->first(),
                false,
            );
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'is_delivered' => $order->status === 'delivered',
            'actors' => $actors,
        ];
    }

    // ── Mes évaluations ──────────────────────────────────────────────────────

    /**
     * Évaluations données et reçues. De l'autre partie, seuls l'identifiant,
     * le nom et le rôle sont transmis : jamais le téléphone, les soldes ni la
     * position (Règles d'or 6 et 22).
     *
     * @return array{given: list<array<string, mixed>>, received: list<array<string, mixed>>}
     */
    public function listFor(User $user): array
    {
        $given = Evaluation::with('evalue')->where('evaluateur_id', $user->id)->latest('id')->get();
        $received = Evaluation::with('evaluateur')->where('evalue_id', $user->id)->latest('id')->get();

        return [
            'given' => $given->map(fn (Evaluation $e) => $this->present($e) + ['evalue' => $this->party($e->evalue)])->all(),
            'received' => $received->map(fn (Evaluation $e) => $this->present($e) + ['evaluateur' => $this->party($e->evaluateur)])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Evaluation $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'mission_id' => $evaluation->mission_id,
            'order_id' => $evaluation->order_id,
            'evaluateur_id' => $evaluation->evaluateur_id,
            'evalue_id' => $evaluation->evalue_id,
            'note' => $evaluation->note,
            'commentaire' => $evaluation->commentaire,
            'fiabilite' => $evaluation->fiabilite,
            'integrite' => $evaluation->integrite,
            'qualite' => $evaluation->qualite,
            'reactivite' => $evaluation->reactivite,
            'created_at' => $evaluation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string|null, role: string}|null
     */
    private function party(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(User $user, ?string $name, string $role, string $roleLabel, string $subtitle, ?Evaluation $evaluation, bool $withCriteria): array
    {
        $given = null;

        if ($evaluation) {
            $given = ['note' => $evaluation->note, 'commentaire' => $evaluation->commentaire];
            if ($withCriteria) {
                $given += [
                    'fiabilite' => $evaluation->fiabilite,
                    'integrite' => $evaluation->integrite,
                    'qualite' => $evaluation->qualite,
                    'reactivite' => $evaluation->reactivite,
                ];
            }
            $given['created_at'] = $evaluation->created_at?->toIso8601String();
        }

        return [
            'id' => $user->id,
            'name' => $name,
            'role' => $role,
            'role_label' => $roleLabel,
            'subtitle' => $subtitle,
            'avatar_url' => $user->avatar_url ?? null,
            'is_evaluated' => $evaluation !== null,
            'evaluation' => $given,
        ];
    }

    /**
     * Fournisseurs ayant servi un bon matériel de la mission.
     *
     * @return Collection<int, int>
     */
    private function supplierIdsOf(Mission $mission): Collection
    {
        $direct = $mission->jcodes()->whereNotNull('fournisseur_id')->pluck('fournisseur_id');
        $byItem = JCodeItem::whereIn('jcode_id', $mission->jcodes()->pluck('id'))
            ->whereNotNull('served_by_supplier_id')
            ->pluck('served_by_supplier_id');

        return $direct->merge($byItem)->unique()->values();
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['evaluation' => $message]);
    }
}
