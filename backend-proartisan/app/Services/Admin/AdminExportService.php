<?php

namespace App\Services\Admin;

use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des listes du backoffice (Chantier C5 / P1-8).
 *
 * Streaming synchrone (fputcsv + curseur) : pas de dépendance file d'attente,
 * adapté à l'hébergement mutualisé. Le fichier est encodé UTF-8 avec BOM et une
 * ligne `sep=;` pour l'ouverture directe dans Excel FR. Chaque export réutilise
 * les mêmes filtres que la page de liste correspondante et est journalisé.
 */
class AdminExportService
{
    /** @var list<string> */
    public const RESOURCES = ['users', 'transactions', 'missions', 'evaluations', 'litiges'];

    public function __construct(private AdminActivityLogger $audit) {}

    public function stream(string $resource, Request $request): StreamedResponse
    {
        [$headers, $query, $mapRow] = match ($resource) {
            'users' => $this->users($request),
            'transactions' => $this->transactions($request),
            'missions' => $this->missions($request),
            'evaluations' => $this->evaluations($request),
            'litiges' => $this->litiges($request),
        };

        $filename = sprintf('prosartisan_%s_%s.csv', $resource, now()->format('Y-m-d_His'));

        $this->audit->log('export.generated', null, [
            'resource' => $resource,
            'filters' => array_filter($request->query()),
            'count' => (clone $query)->count(),
        ]);

        return response()->streamDownload(function () use ($headers, $query, $mapRow): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
            fwrite($out, "sep=;\n");
            fputcsv($out, $headers, ';');

            $query->orderByDesc('created_at')->lazy(500)->each(function ($row) use ($out, $mapRow): void {
                $cells = array_map(static function ($value) {
                    if ($value instanceof \BackedEnum) {
                        return $value->value;
                    }

                    if ($value instanceof \DateTimeInterface) {
                        return $value->format('Y-m-d H:i');
                    }

                    return is_scalar($value) || $value === null ? $value : (string) $value;
                }, $mapRow($row));

                fputcsv($out, $cells, ';');
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: list<string>, 1: Builder, 2: callable}
     */
    private function users(Request $request): array
    {
        $query = User::query()
            ->when($request->query('search_users'), fn (Builder $q, $s) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('id', $s)))
            ->when($request->query('role_users'), fn (Builder $q, $r) => $q->where('role', $r))
            ->when($request->query('kyc_users'), fn (Builder $q, $k) => $q->where('kyc_status', $k));

        return [
            ['ID', 'Nom', 'Téléphone', 'E-mail', 'Rôle', 'Statut KYC', 'Statut compte', 'Score ProsArtisan', 'Score gelé', 'Inscrit le'],
            $query,
            fn ($u) => [
                $u->id, $u->name, $u->phone, $u->email, $u->role, $u->kyc_status,
                $u->account_status, $u->score_prosartisan, $u->score_frozen ? 'oui' : 'non',
                $this->date($u->created_at),
            ],
        ];
    }

    private function transactions(Request $request): array
    {
        $query = Transaction::query()
            ->when($request->query('status_tx'), fn (Builder $q, $s) => $q->where('statut', $s))
            ->when($request->query('provider_tx'), fn (Builder $q, $p) => $q->where('provider', $p))
            ->when($request->query('type_tx'), fn (Builder $q, $t) => $q->where('type', $t))
            ->when($request->query('search_tx'), fn (Builder $q, $s) => $q->where(fn ($sub) => $sub
                ->where('id', $s)->orWhere('reference_externe', 'like', "%{$s}%")
                ->orWhere('wallet_source', 'like', "%{$s}%")->orWhere('wallet_dest', 'like', "%{$s}%")));

        return [
            ['ID', 'Mission', 'Type', 'Montant (FCFA)', 'Provider', 'Statut', 'Wallet source', 'Wallet destination', 'Référence externe', 'Créée le'],
            $query,
            fn ($t) => [
                $t->id, $t->mission_id, $t->type, $t->montant, $t->provider, $t->statut,
                $t->wallet_source, $t->wallet_dest, $t->reference_externe, $this->date($t->created_at),
            ],
        ];
    }

    private function missions(Request $request): array
    {
        $query = Mission::query()
            ->when($request->query('search_mission'), fn (Builder $q, $s) => $q->where(fn ($sub) => $sub
                ->where('id', $s)->orWhere('description', 'like', "%{$s}%")->orWhere('gemini_category', 'like', "%{$s}%")));

        return [
            ['ID', 'Client', 'Artisan', 'Statut', 'Catégorie Gemini', 'Urgence', 'Montant total', 'Montant matériaux', 'Montant MO', 'Référent requis', 'Adresse client', 'Créée le'],
            $query,
            fn ($m) => [
                $m->id, $m->client_id, $m->artisan_id, $m->status, $m->gemini_category, $m->gemini_urgency,
                $m->montant_total, $m->montant_materiaux, $m->montant_mo, $m->referent_required ? 'oui' : 'non',
                $m->client_address, $this->date($m->created_at),
            ],
        ];
    }

    private function evaluations(Request $request): array
    {
        $query = Evaluation::query()
            ->when($request->query('search_eval'), fn (Builder $q, $s) => $q->where(fn ($sub) => $sub
                ->where('id', $s)->orWhere('mission_id', $s)->orWhere('commentaire', 'like', "%{$s}%")));

        return [
            ['ID', 'Mission', 'Évaluateur', 'Évalué', 'Note /5', 'Fiabilité', 'Intégrité', 'Qualité', 'Réactivité', 'Commentaire', 'Créée le'],
            $query,
            fn ($e) => [
                $e->id, $e->mission_id, $e->evaluateur_id, $e->evalue_id, $e->note,
                $e->fiabilite, $e->integrite, $e->qualite, $e->reactivite,
                str_replace(["\n", "\r"], ' ', (string) $e->commentaire), $this->date($e->created_at),
            ],
        ];
    }

    private function litiges(Request $request): array
    {
        $query = Litige::query()
            ->when($request->query('statut_litige'), fn (Builder $q, $s) => $q->where('statut', $s))
            ->when($request->query('search_litige'), fn (Builder $q, $s) => $q->where(fn ($sub) => $sub
                ->where('id', $s)->orWhere('mission_id', $s)->orWhere('description', 'like', "%{$s}%")));

        return [
            ['ID', 'Mission', 'Déclencheur', 'Type', 'Statut', 'Décision', 'Description', 'Résolu le', 'Créé le'],
            $query,
            fn ($l) => [
                $l->id, $l->mission_id, $l->declencheur_id, $l->type, $l->statut, $l->decision,
                str_replace(["\n", "\r"], ' ', (string) $l->description), $this->date($l->resolu_at), $this->date($l->created_at),
            ],
        ];
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i') : '';
    }
}
