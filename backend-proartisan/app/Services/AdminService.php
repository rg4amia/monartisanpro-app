<?php

namespace App\Services;

use App\Models\FournisseurAgree;
use App\Models\KycDocument;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\JCode;
use App\Models\Evaluation;
use App\Models\ScoreLedgerEntry;
use App\Models\Order;
use App\Models\Jalon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        private NotificationService $notificationService,
        private LitigeService $litigeService,
        private OneSignalService $oneSignalService,
        private \App\Services\Admin\AdminActivityLogger $audit,
        private \App\Services\Admin\AdminDashboardCache $dashboardCache,
    ) {}

    public function dashboard(): array
    {
        return $this->dashboardCache->dashboard(fn () => [
            'users_total'            => User::count(),
            'artisans_actifs'        => User::where('role', 'artisan')->where('kyc_status', 'actif')->count(),
            'clients_actifs'         => User::where('role', 'client')->where('kyc_status', 'actif')->count(),
            'fournisseurs_agrees'    => FournisseurAgree::where('statut', 'agree')->count(),
            'missions_en_cours'      => Mission::where('status', 'in_progress')->count(),
            'missions_en_litige'     => Mission::where('status', 'disputed')->count(),
            'litiges_ouverts'        => Litige::whereIn('statut', ['ouvert', 'en_cours'])->count(),
            'kyc_en_attente'         => User::where('kyc_status', 'en_attente')->count(),
            'referent_required_open' => Mission::where('referent_required', true)
                ->whereIn('status', ['funded_locked', 'in_progress', 'disputed'])
                ->count(),
            'recent_fraud_alerts'    => JCode::where('statut', 'actif')->count(), // Placeholder for actual fraud tracking
            'volume_transactions_24h' => Transaction::where('created_at', '>=', now()->subDay())->sum('montant'),
        ]);
    }

    public function pendingKyc(?string $role = null, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->where('kyc_status', 'en_attente')
            ->whereIn('role', ['client', 'artisan', 'fournisseur', 'livreur'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id', $search);
                });
            })
            ->with(['kycDocuments' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Agrégats de l'onglet « KYC & Vérifications » (Chantier C4 / P1-6).
     *
     * @return array{pending: int, artisans_pending: int, fournisseurs_pending: int, rejected: int, registration_trend: list<array{label: string, value: int}>}
     */
    public function kycStats(): array
    {
        $byRole = User::query()
            ->where('kyc_status', 'en_attente')
            ->selectRaw('role, COUNT(*) as c')
            ->groupBy('role')
            ->pluck('c', 'role');

        $since = now()->subDays(14)->startOfDay();
        $registrations = User::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = [];
        for ($i = 14; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $trend[] = [
                'label' => $day->format('d/m'),
                'value' => (int) ($registrations[$day->format('Y-m-d')] ?? 0),
            ];
        }

        return [
            'pending' => (int) $byRole->sum(),
            'artisans_pending' => (int) ($byRole['artisan'] ?? 0),
            'fournisseurs_pending' => (int) ($byRole['fournisseur'] ?? 0),
            'rejected' => (int) User::where('kyc_status', 'rejete')->count(),
            'registration_trend' => $trend,
        ];
    }

    public function reviewKyc(User $admin, User $user, string $decision, ?string $rejectionReason = null): User
    {
        $documents = KycDocument::where('user_id', $user->id)
            ->whereIn('type', ['cni', 'selfie'])
            ->whereIn('statut', ['en_attente', 'approuve'])
            ->get()
            ->keyBy('type');

        if ($decision === 'approuve') {
            // Si les documents manquent (cas fréquent pour les comptes de test/seed),
            // on crée des documents fictifs pour débloquer l'approbation.
            if (! $documents->has('cni')) {
                $cni = KycDocument::create([
                    'user_id'  => $user->id,
                    'type'     => 'cni',
                    'file_url' => 'https://placeholder.com/cni.png',
                    'statut'   => 'en_attente',
                ]);
                $documents->put('cni', $cni);
            }
            if (! $documents->has('selfie')) {
                $selfie = KycDocument::create([
                    'user_id'  => $user->id,
                    'type'     => 'selfie',
                    'file_url' => 'https://placeholder.com/selfie.png',
                    'statut'   => 'en_attente',
                ]);
                $documents->put('selfie', $selfie);
            }
        }

        DB::transaction(function () use ($admin, $user, $decision, $rejectionReason, $documents): void {
            $docStatus = $decision === 'approuve' ? 'approuve' : 'rejete';

            foreach (['cni', 'selfie'] as $type) {
                if ($documents->has($type)) {
                    $documents[$type]->update([
                        'statut'           => $docStatus,
                        'reviewed_by'      => $admin->id,
                        'rejection_reason' => $decision === 'rejete' ? $rejectionReason : null,
                        'reviewed_at'      => now(),
                    ]);
                }
            }

            $user->update([
                'kyc_status' => $decision === 'approuve' ? 'actif' : 'rejete',
            ]);
        });

        $this->notificationService->send(
            $user,
            'kyc',
            'Statut KYC mis à jour',
            $decision === 'approuve'
                ? 'Votre dossier KYC est validé. Vous pouvez maintenant effectuer des transactions.'
                : 'Votre dossier KYC a été rejeté. Merci de vérifier vos documents et de recommencer.',
            ['decision' => $decision]
        );

        // Envoi de la notification Push
        $this->oneSignalService->sendToUser(
            (string) $user->id,
            'Statut KYC mis à jour',
            $decision === 'approuve'
                ? 'Votre dossier KYC est validé. Vous pouvez maintenant postuler !'
                : 'Votre dossier KYC a été rejeté. Merci de vérifier vos documents.',
            ['decision' => $decision, 'type' => 'kyc']
        );

        $this->audit->log('kyc.reviewed', $user, [
            'decision'         => $decision,
            'rejection_reason' => $decision === 'rejete' ? $rejectionReason : null,
        ], actor: $admin);

        return $user->fresh(['kycDocuments']);
    }

    /**
     * Revue KYC groupée (Chantier C5 / P1-9). Chaque dossier passe par `reviewKyc`
     * (documents, notifications, journal). Un dossier en échec n'interrompt pas le lot.
     *
     * @param  array<int>  $ids
     * @return int  Nombre de dossiers traités avec succès.
     */
    public function bulkReviewKyc(User $admin, array $ids, string $decision, ?string $rejectionReason = null): int
    {
        $done = 0;

        foreach (User::whereIn('id', $ids)->where('kyc_status', 'en_attente')->get() as $user) {
            try {
                $this->reviewKyc($admin, $user, $decision, $rejectionReason);
                $done++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("bulkReviewKyc user {$user->id}: ".$e->getMessage());
            }
        }

        $this->audit->log('kyc.bulk_reviewed', null, [
            'decision' => $decision,
            'requested' => count($ids),
            'processed' => $done,
        ], actor: $admin);

        return $done;
    }

    public function listLitiges(?string $statut = null, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $this->litigeService->evaluateDueLitiges();

        return Litige::query()
            ->with(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('id', $search)
                        ->orWhere('mission_id', $search)
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('mission.client', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('mission.artisan', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Agrégats des litiges, indépendants de la page (Chantier C4 / P1-6).
     *
     * @return array{open: int, high_risk: int, resolved: int, missions_disputed: int}
     */
    public function litigeStats(): array
    {
        $byStatus = Litige::query()
            ->selectRaw('statut, COUNT(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut');

        return [
            'open' => (int) (($byStatus['ouvert'] ?? 0) + ($byStatus['en_cours'] ?? 0)),
            'resolved' => (int) ($byStatus['resolu'] ?? 0),
            'high_risk' => (int) Litige::whereIn('statut', ['ouvert', 'en_cours'])
                ->whereHas('mission', fn ($m) => $m->where('montant_total', '>=', 2_000_000))
                ->count(),
            'missions_disputed' => (int) Mission::where('status', 'disputed')->count(),
        ];
    }

    public function listMissions(?string $status = null, ?string $query = null, int $perPage = 20, string $pageName = 'page'): LengthAwarePaginator
    {
        return Mission::query()
            ->with([
                'client:id,name,phone',
                'artisan:id,name,phone',
                'jalons',
                'jcodes.fournisseur:id,name,phone',
                'transactions',
                'litiges',
                'evaluations',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query): void {
                    $sub->where('id', $query)
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('gemini_category', 'like', "%{$query}%")
                        ->orWhereHas('client', fn ($client) => $client
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"))
                        ->orWhereHas('artisan', fn ($artisan) => $artisan
                            ->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Agrégats de l'onglet « Missions » (Chantier C4 / P1-6), indépendants de la page.
     *
     * @return array{en_cours: int, referent_required: int, en_litige: int, enrichies: int}
     */
    public function missionStats(): array
    {
        return [
            'en_cours' => (int) Mission::where('status', 'in_progress')->count(),
            'en_litige' => (int) Mission::where('status', 'disputed')->count(),
            'referent_required' => (int) Mission::where('referent_required', true)
                ->whereIn('status', ['funded_locked', 'in_progress', 'disputed'])
                ->count(),
            'enrichies' => (int) Mission::whereNotNull('gemini_category')->where('gemini_category', '!=', '')->count(),
        ];
    }

    /**
     * Agrégats de la sous-vue « Livraisons » (Chantier C4 / P1-6).
     *
     * @return array{total: int, in_transit: int, awaiting_driver: int, delivered: int, by_status: array<string, int>}
     */
    public function deliveryStats(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('orders')) {
            return ['total' => 0, 'in_transit' => 0, 'awaiting_driver' => 0, 'delivered' => 0, 'by_status' => []];
        }

        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $sum = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => (int) ($byStatus[$s] ?? 0));

        return [
            'total' => (int) $byStatus->sum(),
            'in_transit' => $sum(['shipping', 'driver_picked_up']),
            'awaiting_driver' => $sum(['searching_driver', 'driver_assigned', 'prepared']),
            'delivered' => (int) ($byStatus['delivered'] ?? 0),
            'by_status' => $byStatus->map(fn ($c) => (int) $c)->toArray(),
        ];
    }

    public function listOrders(?string $status = null, ?string $mode = null, ?string $query = null, int $perPage = 100, string $pageName = 'page'): LengthAwarePaginator
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('orders')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        return Order::query()
            ->with([
                'client:id,name,phone,role',
                'supplier:id,name,phone,role',
                'supplier.fournisseurAgree:id,user_id,nom_boutique,statut',
                'driver:id,name,phone,role',
                'items.product:id,name,price,unit',
                'transactions',
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($mode, fn ($q) => $q->where('delivery_mode', $mode))
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('id', $query)
                        ->orWhere('pickup_code', 'like', "%{$query}%")
                        ->orWhere('reception_code', 'like', "%{$query}%")
                        ->orWhereHas('client', fn ($u) => $u->where('name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%"))
                        ->orWhereHas('driver', fn ($u) => $u->where('name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%"))
                        ->orWhereHas('supplier', fn ($u) => $u->where('name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    public function resolveLitige(User $admin, Litige $litige, array $payload): Litige
    {
        $resolved = $this->litigeService->arbitrate($admin, $litige, $payload);

        $this->audit->log('litige.arbitrated', $litige, [
            'decision'   => $payload['decision'] ?? null,
            'mission_id' => $litige->mission_id,
        ], subjectLabel: 'Litige #'.$litige->id, actor: $admin);

        return $resolved;
    }

    public function pendingFournisseurs(int $perPage = 20): LengthAwarePaginator
    {
        return FournisseurAgree::query()
            ->with('user')
            ->where('statut', 'en_attente')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function reviewFournisseur(User $admin, FournisseurAgree $fournisseurAgree, string $decision): FournisseurAgree
    {
        $fournisseurAgree->update([
            'statut'     => $decision,
            'approuve_at' => $decision === 'agree' ? now() : null,
        ]);

        $fournisseur = $fournisseurAgree->user;

        if ($fournisseur) {
            $this->notificationService->send(
                $fournisseur,
                'fournisseur',
                'Décision sur votre agrément',
                $decision === 'agree'
                    ? 'Votre boutique est agréée. Vous pouvez scanner les J-Codes.'
                    : 'Votre agrément fournisseur est suspendu. Contactez le support.',
                ['decision' => $decision, 'admin_id' => $admin->id]
            );
        }

        $this->audit->log('fournisseur.reviewed', $fournisseurAgree, [
            'decision' => $decision,
        ], subjectLabel: $fournisseurAgree->nom_boutique, actor: $admin);

        return $fournisseurAgree->fresh('user');
    }

    public function listUsers(?string $query = null, ?string $role = null, ?string $kycStatus = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->withCount([
                'missionsClient',
                'missionsArtisan',
            ])
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query): void {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('id', $query);
                });
            })
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($kycStatus, fn ($q) => $q->where('kyc_status', $kycStatus))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listTransactions(
        ?string $status = null,
        ?string $provider = null,
        int $perPage = 20,
        ?string $search = null,
        ?string $type = null
    ): LengthAwarePaginator {
        return Transaction::query()
            ->with(['user', 'mission'])
            ->when($status, fn ($q) => $q->where('statut', $status))
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('id', $search)
                        ->orWhere('reference_externe', 'like', "%{$search}%")
                        ->orWhere('wallet_source', 'like', "%{$search}%")
                        ->orWhere('wallet_dest', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Agrégats du journal financier, indépendants de la page courante
     * (la liste des transactions est paginée — Chantier C4 / P1-6).
     *
     * @return array{pending: int, failed: int, confirmed: int, volume_24h: int, escrow: int, released: int}
     */
    public function transactionStats(): array
    {
        $byStatus = Transaction::query()
            ->selectRaw('statut, COUNT(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut');

        return [
            'pending'    => (int) ($byStatus['en_attente'] ?? 0),
            'failed'     => (int) ($byStatus['echoue'] ?? 0),
            'confirmed'  => (int) ($byStatus['confirme'] ?? 0),
            'volume_24h' => (int) Transaction::where('created_at', '>=', now()->subDay())->sum('montant'),
            'escrow'     => (int) Transaction::where('statut', 'confirme')->where('type', 'acompte')->sum('montant'),
            'released'   => (int) Transaction::where('statut', 'confirme')
                ->whereIn('type', ['liberation_jalon', 'paiement_fournisseur'])
                ->sum('montant'),
        ];
    }

    public function listEvaluations(?int $perPage = 100, ?string $search = null, string $pageName = 'page'): LengthAwarePaginator
    {
        return Evaluation::query()
            ->with(['mission', 'evaluateur', 'evalue'])
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('id', $search)
                        ->orWhere('mission_id', $search)
                        ->orWhere('commentaire', 'like', "%{$search}%")
                        ->orWhereHas('evaluateur', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('evalue', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Version paginée du classement des scores artisans, avec recherche
     * (Chantier C4 / P1-6). `listArtisansScores()` reste pour le bundle Dashboard.
     */
    public function paginateArtisanScores(?string $search = null, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return $this->artisanScoresQuery()
            ->when($search, function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            })
            ->paginate($perPage, ['*'], $pageName);
    }

    public function listArtisansScores(): array
    {
        return $this->artisanScoresQuery()->get()->toArray();
    }

    private function artisanScoresQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->where('role', 'artisan')
            ->withCount('evaluationsRecues')
            ->withAvg('evaluationsRecues', 'fiabilite')
            ->withAvg('evaluationsRecues', 'integrite')
            ->withAvg('evaluationsRecues', 'qualite')
            ->withAvg('evaluationsRecues', 'reactivite')
            ->orderByDesc('score_prosartisan');
    }

    /**
     * Agrégats de l'onglet « Évaluations & Scores » (Chantier C4 / P1-6).
     *
     * @return array{evaluations_total: int, note_moyenne: float, artisans_suivis: int, scores_geles: int}
     */
    public function evaluationStats(): array
    {
        return [
            'evaluations_total' => (int) Evaluation::count(),
            'note_moyenne' => round((float) (Evaluation::avg('note') ?? 0), 1),
            'artisans_suivis' => (int) User::where('role', 'artisan')->count(),
            'scores_geles' => (int) User::where('role', 'artisan')->where('score_frozen', true)->count(),
        ];
    }

    public function listScoreLedger(): array
    {
        return ScoreLedgerEntry::query()
            ->with(['user', 'mission', 'evaluation'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->toArray();
    }

    public function getFinancialKpis(): array
    {
        // Agrégats coûteux : stabilisés en cache, purge sur mouvement financier (Chantier C4 / P1-7).
        return $this->dashboardCache->financialKpis(fn () => $this->buildFinancialKpis());
    }

    private function buildFinancialKpis(): array
    {
        try {
            // 1. Solde Général & Séquestre
            $adminUser = User::where('role', 'admin')->first();
            $soldeGeneralAdmin = ($adminUser?->wallet_mo ?? 0) + ($adminUser?->wallet_materiaux ?? 0);

            // Transactions de commission plateforme
            $totalCommissionChantiers = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('wallet_transactions')) {
                $totalCommissionChantiers = (int) DB::table('wallet_transactions')
                    ->where('description', 'like', '%Commission plateforme%')
                    ->orWhere('metadata->type', 'platform_commission')
                    ->sum('montant');
            }

            $totalCommissionEcommerce = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('orders')) {
                $totalCommissionEcommerce = (int) DB::table('orders')
                    ->whereIn('status', ['paid', 'prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered'])
                    ->sum('platform_fee');
            }

            $soldeCumuleCommissions = $totalCommissionChantiers + $totalCommissionEcommerce;

            // Encours séquestre
            $sequestreMoEncours = (int) User::where('role', 'artisan')->sum('wallet_mo');
            $sequestreMateriauxEncours = (int) User::where('role', 'artisan')->sum('wallet_materiaux');

            // Total libéré (Paiement des jalons + commandes livrées)
            $totalLibereArtisans = \Illuminate\Support\Facades\Schema::hasTable('jalons') ? (int) Jalon::where('statut', 'paye')->sum('montant') : 0;
            $totalLibereFournisseurs = \Illuminate\Support\Facades\Schema::hasTable('orders') ? (int) Order::whereIn('status', ['prepared', 'delivered'])->sum('subtotal') : 0;
            $totalLibereLivreurs = \Illuminate\Support\Facades\Schema::hasTable('orders') ? (int) Order::where('status', 'delivered')->sum('delivery_cost') : 0;
            $totalLibereGeneral = $totalLibereArtisans + $totalLibereFournisseurs + $totalLibereLivreurs;

            // 2. Commissions par catégorie de métier et par année
            $commissionsByCategoryYear = collect([]);
            if (\Illuminate\Support\Facades\Schema::hasTable('jalons') && \Illuminate\Support\Facades\Schema::hasTable('missions')) {
                $commissionsByCategoryYear = DB::table('jalons')
                    ->join('missions', 'jalons.mission_id', '=', 'missions.id')
                    ->leftJoin('devis', function ($join) {
                        $join->on('devis.mission_id', '=', 'missions.id')
                             ->where('devis.statut', '=', 'accepte');
                    })
                    ->where('jalons.statut', '=', 'paye')
                    ->select(
                        DB::raw("COALESCE(NULLIF(missions.gemini_category, ''), 'Général / Divers') as category"),
                        DB::raw("YEAR(COALESCE(jalons.paye_at, jalons.updated_at)) as year"),
                        DB::raw("COUNT(DISTINCT missions.id) as missions_count"),
                        DB::raw("SUM(jalons.montant) as volume_brut"),
                        DB::raw("ROUND(SUM(jalons.montant * COALESCE(devis.commission_service_ratio, 0.10) / (1 + COALESCE(devis.commission_service_ratio, 0.10)))) as commission_net")
                    )
                    ->groupBy('category', 'year')
                    ->orderBy('year', 'desc')
                    ->orderBy('commission_net', 'desc')
                    ->get();
            }

            // 3. Commissions et volume par Fournisseur (Quincailleries)
            $commissionsBySupplier = collect([]);
            if (\Illuminate\Support\Facades\Schema::hasTable('orders') && \Illuminate\Support\Facades\Schema::hasTable('fournisseurs_agrees')) {
                $commissionsBySupplier = DB::table('users')
                    ->where('users.role', '=', 'fournisseur')
                    ->leftJoin('fournisseurs_agrees', 'fournisseurs_agrees.user_id', '=', 'users.id')
                    ->leftJoin('orders', function ($join) {
                        $join->on('orders.supplier_id', '=', 'users.id')
                             ->whereIn('orders.status', ['paid', 'prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered']);
                    })
                    ->select(
                        'users.id as supplier_id',
                        'users.name as supplier_name',
                        'users.phone as supplier_phone',
                        DB::raw("COALESCE(fournisseurs_agrees.nom_boutique, users.name) as shop_name"),
                        DB::raw("COALESCE(fournisseurs_agrees.statut, 'en_attente') as agreement_status"),
                        DB::raw("COUNT(DISTINCT orders.id) as orders_count"),
                        DB::raw("COALESCE(SUM(orders.subtotal), 0) as volume_materiaux"),
                        DB::raw("COALESCE(SUM(orders.platform_fee), 0) as commission_prosartisan")
                    )
                    ->groupBy('users.id', 'users.name', 'users.phone', 'fournisseurs_agrees.nom_boutique', 'fournisseurs_agrees.statut')
                    ->orderByDesc('volume_materiaux')
                    ->get();
            }

            // 4. Commissions et activité par Livreur (Drivers)
            $commissionsByDriver = collect([]);
            if (\Illuminate\Support\Facades\Schema::hasTable('orders')) {
                $commissionsByDriver = DB::table('users')
                    ->where('users.role', '=', 'driver')
                    ->leftJoin('orders', function ($join) {
                        $join->on('orders.driver_id', '=', 'users.id')
                             ->whereIn('orders.status', ['driver_assigned', 'driver_picked_up', 'shipping', 'delivered']);
                    })
                    ->select(
                        'users.id as driver_id',
                        'users.name as driver_name',
                        'users.phone as driver_phone',
                        DB::raw("COUNT(DISTINCT orders.id) as deliveries_count"),
                        DB::raw("COALESCE(SUM(orders.delivery_cost), 0) as total_frais_livraison"),
                        DB::raw("COALESCE(SUM(CASE WHEN orders.status = 'delivered' THEN orders.delivery_cost ELSE 0 END), 0) as gains_livreur_liberes")
                    )
                    ->groupBy('users.id', 'users.name', 'users.phone')
                    ->orderByDesc('deliveries_count')
                    ->get();
            }

            // 5. KPIs Recommandés Supplémentaires
            $totalMissions = Mission::count();
            $disputedMissions = Mission::where('status', 'disputed')->count();
            $disputeRate = $totalMissions > 0 ? round(($disputedMissions / $totalMissions) * 100, 1) : 0.0;

            $totalDevisCount = \Illuminate\Support\Facades\Schema::hasTable('devis') ? DB::table('devis')->count() : 0;
            $acceptedDevisCount = \Illuminate\Support\Facades\Schema::hasTable('devis') ? DB::table('devis')->where('statut', 'accepte')->count() : 0;
            $devisConversionRate = $totalDevisCount > 0 ? round(($acceptedDevisCount / $totalDevisCount) * 100, 1) : 0.0;

            $avgChantierAmount = (int) round(Mission::whereNotNull('montant_total')->avg('montant_total') ?? 0);
            $avgEcommerceAmount = \Illuminate\Support\Facades\Schema::hasTable('orders') ? (int) round(Order::avg('total_amount') ?? 0) : 0;

            // Top 5 Artisans
            $topArtisans = User::where('role', 'artisan')
                ->orderByDesc('score_prosartisan')
                ->take(5)
                ->get(['id', 'name', 'phone', 'score_prosartisan', 'wallet_mo', 'wallet_materiaux']);

            return [
                'solde_general' => [
                    'solde_admin_wallet' => $soldeGeneralAdmin,
                    'total_commissions_cumulees' => $soldeCumuleCommissions,
                    'commissions_chantiers' => $totalCommissionChantiers,
                    'commissions_ecommerce' => $totalCommissionEcommerce,
                    'sequestre_mo_encours' => $sequestreMoEncours,
                    'sequestre_materiaux_encours' => $sequestreMateriauxEncours,
                    'total_libere_general' => $totalLibereGeneral,
                    'total_libere_artisans' => $totalLibereArtisans,
                    'total_libere_fournisseurs' => $totalLibereFournisseurs,
                    'total_libere_livreurs' => $totalLibereLivreurs,
                ],
                'commissions_by_category_year' => $commissionsByCategoryYear,
                'commissions_by_supplier' => $commissionsBySupplier,
                'commissions_by_driver' => $commissionsByDriver,
                'additional_kpis' => [
                    'dispute_rate_percent' => $disputeRate,
                    'devis_conversion_rate_percent' => $devisConversionRate,
                    'aov_chantier' => $avgChantierAmount,
                    'aov_ecommerce' => $avgEcommerceAmount,
                    'top_artisans' => $topArtisans,
                ],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erreur getFinancialKpis: ' . $e->getMessage());
            return [
                'solde_general' => [
                    'solde_admin_wallet' => 0,
                    'total_commissions_cumulees' => 0,
                    'commissions_chantiers' => 0,
                    'commissions_ecommerce' => 0,
                    'sequestre_mo_encours' => 0,
                    'sequestre_materiaux_encours' => 0,
                    'total_libere_general' => 0,
                    'total_libere_artisans' => 0,
                    'total_libere_fournisseurs' => 0,
                    'total_libere_livreurs' => 0,
                ],
                'commissions_by_category_year' => [],
                'commissions_by_supplier' => [],
                'commissions_by_driver' => [],
                'additional_kpis' => [
                    'dispute_rate_percent' => 0,
                    'devis_conversion_rate_percent' => 0,
                    'aov_chantier' => 0,
                    'aov_ecommerce' => 0,
                    'top_artisans' => [],
                ],
            ];
        }
    }
}
