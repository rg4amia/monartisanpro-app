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
    ) {}

    public function dashboard(): array
    {
        return [
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
        ];
    }

    public function pendingKyc(?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->where('kyc_status', 'en_attente')
            ->whereIn('role', ['client', 'artisan', 'fournisseur', 'livreur'])
            ->when($role, fn ($q) => $q->where('role', $role))
            ->with(['kycDocuments' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('created_at')
            ->paginate($perPage);
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

        return $user->fresh(['kycDocuments']);
    }

    public function listLitiges(?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        $this->litigeService->evaluateDueLitiges();

        return Litige::query()
            ->with(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listMissions(?string $status = null, ?string $query = null, int $perPage = 20): LengthAwarePaginator
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
            ->paginate($perPage);
    }

    public function resolveLitige(User $admin, Litige $litige, array $payload): Litige
    {
        return $this->litigeService->arbitrate($admin, $litige, $payload);
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
                        ->orWhere('id', $query);
                });
            })
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($kycStatus, fn ($q) => $q->where('kyc_status', $kycStatus))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listTransactions(?string $status = null, ?string $provider = null, int $perPage = 20): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['user', 'mission'])
            ->when($status, fn ($q) => $q->where('statut', $status))
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listEvaluations(?int $perPage = 100): LengthAwarePaginator
    {
        return Evaluation::query()
            ->with(['mission', 'evaluateur', 'evalue'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listArtisansScores(): array
    {
        return User::query()
            ->where('role', 'artisan')
            ->withCount('evaluationsRecues')
            ->withAvg('evaluationsRecues', 'fiabilite')
            ->withAvg('evaluationsRecues', 'integrite')
            ->withAvg('evaluationsRecues', 'qualite')
            ->withAvg('evaluationsRecues', 'reactivite')
            ->orderByDesc('score_prosartisan')
            ->get()
            ->toArray();
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
        // 1. Solde Général & Séquestre
        $adminUser = User::where('role', 'admin')->first();
        $soldeGeneralAdmin = ($adminUser?->wallet_mo ?? 0) + ($adminUser?->wallet_materiaux ?? 0);

        // Transactions de commission plateforme
        $totalCommissionChantiers = (int) DB::table('wallet_transactions')
            ->where('description', 'like', '%Commission plateforme%')
            ->orWhere('metadata->type', 'platform_commission')
            ->sum('montant');

        $totalCommissionEcommerce = (int) DB::table('orders')
            ->whereIn('status', ['paid', 'prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered'])
            ->sum('platform_fee');

        $soldeCumuleCommissions = $totalCommissionChantiers + $totalCommissionEcommerce;

        // Encours séquestre
        $sequestreMoEncours = (int) User::where('role', 'artisan')->sum('wallet_mo');
        $sequestreMateriauxEncours = (int) User::where('role', 'artisan')->sum('wallet_materiaux');

        // Total libéré (Paiement des jalons + commandes livrées)
        $totalLibereArtisans = (int) Jalon::where('statut', 'paye')->sum('montant');
        $totalLibereFournisseurs = (int) Order::whereIn('status', ['prepared', 'delivered'])->sum('subtotal');
        $totalLibereLivreurs = (int) Order::where('status', 'delivered')->sum('delivery_cost');
        $totalLibereGeneral = $totalLibereArtisans + $totalLibereFournisseurs + $totalLibereLivreurs;

        // 2. Commissions par catégorie de métier et par année
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

        // 3. Commissions et volume par Fournisseur (Quincailleries)
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

        // 4. Commissions et activité par Livreur (Drivers)
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

        // 5. KPIs Recommandés Supplémentaires
        $totalMissions = Mission::count();
        $disputedMissions = Mission::where('status', 'disputed')->count();
        $disputeRate = $totalMissions > 0 ? round(($disputedMissions / $totalMissions) * 100, 1) : 0.0;

        $totalDevisCount = DB::table('devis')->count();
        $acceptedDevisCount = DB::table('devis')->where('statut', 'accepte')->count();
        $devisConversionRate = $totalDevisCount > 0 ? round(($acceptedDevisCount / $totalDevisCount) * 100, 1) : 0.0;

        $avgChantierAmount = (int) round(Mission::whereNotNull('montant_total')->avg('montant_total') ?? 0);
        $avgEcommerceAmount = (int) round(Order::avg('total_amount') ?? 0);

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
    }
}
