<?php

namespace App\Services\Admin;

use App\Models\Commune;
use App\Models\Evaluation;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminTerritoryService
{
    public function __construct(private AdminDashboardCache $dashboardCache) {}

    /**
     * Référentiel des 14 Districts / Régions de Côte d'Ivoire.
     */
    public const DISTRICTS = [
        'abidjan' => [
            'id' => 'abidjan',
            'name' => "District Autonome d'Abidjan",
            'short_name' => 'Abidjan',
            'chef_lieu' => 'Abidjan',
            'lat' => 5.3599,
            'lng' => -4.0083,
            'villes' => ['Abidjan', 'Abobo', 'Adjamé', 'Attécoubé', 'Cocody', 'Koumassi', 'Marcory', 'Plateau', 'Port-Bouët', 'Treichville', 'Yopougon', 'Anyama', 'Bingerville', 'Songon'],
        ],
        'yamoussoukro' => [
            'id' => 'yamoussoukro',
            'name' => "District Autonome de Yamoussoukro",
            'short_name' => 'Yamoussoukro',
            'chef_lieu' => 'Yamoussoukro',
            'lat' => 6.8276,
            'lng' => -5.2893,
            'villes' => ['Yamoussoukro', 'Attiégouakro'],
        ],
        'gbeke' => [
            'id' => 'gbeke',
            'name' => 'Vallée du Bandama (Gbêkê)',
            'short_name' => 'Bouaké / Gbêkê',
            'chef_lieu' => 'Bouaké',
            'lat' => 7.6906,
            'lng' => -5.0397,
            'villes' => ['Bouaké', 'Béoumi', 'Sakassou', 'Botro', 'Katiola'],
        ],
        'san_pedro' => [
            'id' => 'san_pedro',
            'name' => 'Bas-Sassandra (San-Pédro)',
            'short_name' => 'San-Pédro',
            'chef_lieu' => 'San-Pédro',
            'lat' => 4.7485,
            'lng' => -6.6363,
            'villes' => ['San-Pédro', 'Sassandra', 'Tabou', 'Soubré'],
        ],
        'poro' => [
            'id' => 'poro',
            'name' => 'Savanes (Poro / Tchologo)',
            'short_name' => 'Korhogo / Savanes',
            'chef_lieu' => 'Korhogo',
            'lat' => 9.4580,
            'lng' => -5.6296,
            'villes' => ['Korhogo', 'Ferkessédougou', 'Boundiali', 'Ouangolodougou'],
        ],
        'haut_sassandra' => [
            'id' => 'haut_sassandra',
            'name' => 'Haut-Sassandra (Daloa)',
            'short_name' => 'Daloa',
            'chef_lieu' => 'Daloa',
            'lat' => 6.8774,
            'lng' => -6.4502,
            'villes' => ['Daloa', 'Issia', 'Vavoua', 'Zoukougbeu'],
        ],
        'tonkpi' => [
            'id' => 'tonkpi',
            'name' => 'Montagnes (Tonkpi / Guémon / Cavally)',
            'short_name' => 'Man / Montagnes',
            'chef_lieu' => 'Man',
            'lat' => 7.4125,
            'lng' => -7.5544,
            'villes' => ['Man', 'Danané', 'Biankouma', 'Guiglo', 'Duékoué'],
        ],
        'belier' => [
            'id' => 'belier',
            'name' => 'Lacs (Bélier / N\'Zi / Iffou)',
            'short_name' => 'Toumodi / Dimbokro',
            'chef_lieu' => 'Dimbokro',
            'lat' => 6.6468,
            'lng' => -4.7052,
            'villes' => ['Dimbokro', 'Toumodi', 'Tiébissou', 'Daoukro', 'Bongouanou'],
        ],
        'agneby_tiassa' => [
            'id' => 'agneby_tiassa',
            'name' => 'Lagunes (Agnéby-Tiassa / Grands-Ponts / La Mé)',
            'short_name' => 'Agboville / Dabou',
            'chef_lieu' => 'Agboville',
            'lat' => 5.9280,
            'lng' => -4.2132,
            'villes' => ['Agboville', 'Dabou', 'Tiassalé', 'Adzopé', 'Grand-Lahou', 'Sikensi'],
        ],
        'goh_djiboua' => [
            'id' => 'goh_djiboua',
            'name' => 'Gôh-Djiboua (Gagnoa / Lôh-Djiboua)',
            'short_name' => 'Gagnoa / Divo',
            'chef_lieu' => 'Gagnoa',
            'lat' => 6.1319,
            'lng' => -5.9506,
            'villes' => ['Gagnoa', 'Divo', 'Lakota', 'Oumé', 'Guibéroua'],
        ],
        'indenie_djuablin' => [
            'id' => 'indenie_djuablin',
            'name' => 'Comoé (Indénié-Djuablin / Sud-Comoé)',
            'short_name' => 'Abengourou / Bassam',
            'chef_lieu' => 'Abengourou',
            'lat' => 6.7297,
            'lng' => -3.4964,
            'villes' => ['Abengourou', 'Grand-Bassam', 'Aboisso', 'Agnibilékrou', 'Bonoua'],
        ],
        'gontougo' => [
            'id' => 'gontougo',
            'name' => 'Zanzan (Gontougo / Bounkani)',
            'short_name' => 'Bondoukou',
            'chef_lieu' => 'Bondoukou',
            'lat' => 8.0402,
            'lng' => -2.8000,
            'villes' => ['Bondoukou', 'Bouna', 'Tanda', 'Koun-Fao'],
        ],
        'worodougou' => [
            'id' => 'worodougou',
            'name' => 'Woroba (Worodougou / Béré / Bafing)',
            'short_name' => 'Séguéla',
            'chef_lieu' => 'Séguéla',
            'lat' => 7.9611,
            'lng' => -6.6731,
            'villes' => ['Séguéla', 'Touba', 'Mankono', 'Kani'],
        ],
        'denguele' => [
            'id' => 'denguele',
            'name' => 'Denguélé (Kabadougou / Folon)',
            'short_name' => 'Odienné',
            'chef_lieu' => 'Odienné',
            'lat' => 9.5051,
            'lng' => -7.5643,
            'villes' => ['Odienné', 'Minignan', 'Madinani'],
        ],
    ];

    /**
     * Référentiel des 13 Communes du Grand Abidjan.
     */
    public const ABIDJAN_COMMUNES = [
        'cocody' => ['name' => 'Cocody', 'type' => 'résidentiel / affaires', 'lat' => 5.3544, 'lng' => -3.9856],
        'yopougon' => ['name' => 'Yopougon', 'type' => 'populaire / industrie', 'lat' => 5.3400, 'lng' => -4.0800],
        'plateau' => ['name' => 'Plateau', 'type' => 'centre des affaires', 'lat' => 5.3261, 'lng' => -4.0197],
        'abobo' => ['name' => 'Abobo', 'type' => 'populaire / artisanat', 'lat' => 5.4167, 'lng' => -4.0167],
        'marcory' => ['name' => 'Marcory', 'type' => 'résidentiel / commercial', 'lat' => 5.3000, 'lng' => -3.9833],
        'koumassi' => ['name' => 'Koumassi', 'type' => 'artisanal / commercial', 'lat' => 5.3000, 'lng' => -3.9500],
        'treichville' => ['name' => 'Treichville', 'type' => 'portuaire / commercial', 'lat' => 5.3000, 'lng' => -4.0000],
        'port_bouet' => ['name' => 'Port-Bouët', 'type' => 'aéroportuaire / littoral', 'lat' => 5.2500, 'lng' => -3.9333],
        'attecoube' => ['name' => 'Attécoubé', 'type' => 'artisanal / lagune', 'lat' => 5.3333, 'lng' => -4.0333],
        'adjame' => ['name' => 'Adjamé', 'type' => 'commercial / carrefour', 'lat' => 5.3500, 'lng' => -4.0333],
        'bingerville' => ['name' => 'Bingerville', 'type' => 'périurbain / résidentiel', 'lat' => 5.3556, 'lng' => -3.8861],
        'anyama' => ['name' => 'Anyama', 'type' => 'périurbain / stade olympique', 'lat' => 5.4944, 'lng' => -4.0519],
        'songon' => ['name' => 'Songon', 'type' => 'périurbain ouest / littoral', 'lat' => 5.3167, 'lng' => -4.2500],
    ];

    /**
     * Calcule la synthèse opérationnelle et territoriale d'une zone (nationale, district ou commune).
     */
    public function getTerritorySummary(?string $districtSlug = null, ?string $communeSlug = null): array
    {
        $communeModel = null;
        if ($communeSlug) {
            $communeModel = Commune::where('slug', $communeSlug)->first();
        }

        // Scope utilisateurs
        $userQuery = User::query();
        $this->applyUserLocationScope($userQuery, $districtSlug, $communeSlug, $communeModel);

        $countsByRole = (clone $userQuery)
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        $artisansActiveKyc = (clone $userQuery)
            ->where('role', 'artisan')
            ->where('kyc_status', 'actif')
            ->count();

        // Scope missions
        $missionQuery = Mission::query();
        $this->applyMissionLocationScope($missionQuery, $districtSlug, $communeSlug, $communeModel);

        $missionsTotal = (clone $missionQuery)->count();
        $missionsCompleted = (clone $missionQuery)->where('status', 'completed')->count();
        $missionsInProgress = (clone $missionQuery)->whereIn('status', ['funded_locked', 'in_progress'])->count();
        $missionsDisputed = (clone $missionQuery)->where('status', 'disputed')->count();

        $totalVolumeFcfa = (int) ((clone $missionQuery)->sum('montant_total') ?? 0);

        // Taux de réalisation & taux de litige
        $realizationRate = $missionsTotal > 0 ? round(($missionsCompleted / $missionsTotal) * 100, 1) : 0;
        $disputeRate = $missionsTotal > 0 ? round(($missionsDisputed / $missionsTotal) * 100, 1) : 0;

        // Note moyenne des évaluations
        $missionIds = (clone $missionQuery)->pluck('id');
        $avgRating = 0;
        if ($missionIds->isNotEmpty()) {
            $avgRating = round((float) Evaluation::whereIn('mission_id', $missionIds)->avg('note') ?: 0, 1);
        }

        // Score ProsArtisan moyen des artisans
        $avgScore = (int) round((clone $userQuery)->where('role', 'artisan')->avg('score_prosartisan') ?: 0);

        $selectedZoneName = "Toute la Côte d'Ivoire (Vue Nationale)";
        if ($communeSlug && isset(self::ABIDJAN_COMMUNES[$communeSlug])) {
            $selectedZoneName = "Commune de " . self::ABIDJAN_COMMUNES[$communeSlug]['name'] . " (Grand Abidjan)";
        } elseif ($districtSlug && isset(self::DISTRICTS[$districtSlug])) {
            $selectedZoneName = self::DISTRICTS[$districtSlug]['name'];
        }

        return [
            'zone' => [
                'type' => $communeSlug ? 'commune' : ($districtSlug ? 'district' : 'national'),
                'slug' => $communeSlug ?: ($districtSlug ?: 'all'),
                'name' => $selectedZoneName,
                'district_slug' => $districtSlug,
                'commune_slug' => $communeSlug,
            ],
            'actors' => [
                'clients' => (int) ($countsByRole['client'] ?? 0),
                'artisans' => (int) ($countsByRole['artisan'] ?? 0),
                'artisans_kyc_actif' => $artisansActiveKyc,
                'fournisseurs' => (int) ($countsByRole['fournisseur'] ?? 0),
                'livreurs' => (int) ($countsByRole['livreur'] ?? 0),
                'total_actors' => (int) array_sum($countsByRole),
            ],
            'missions' => [
                'total' => $missionsTotal,
                'en_cours' => $missionsInProgress,
                'completed' => $missionsCompleted,
                'terminee' => $missionsCompleted,
                'disputed' => $missionsDisputed,
                'litige' => $missionsDisputed,
                'realization_rate' => $realizationRate,
                'dispute_rate' => $disputeRate,
                'total_volume_fcfa' => $totalVolumeFcfa,
                'financial_volume_fcfa' => $totalVolumeFcfa,
            ],
            'reputation' => [
                'avg_rating' => $avgRating,
                'average_artisan_rating' => $avgRating,
                'avg_score_prosartisan' => $avgScore,
                'total_reviews' => $missionIds->isNotEmpty() ? Evaluation::whereIn('mission_id', $missionIds)->count() : 0,
            ],
        ];
    }

    /**
     * Ventilation pour coloration cartographique par District.
     */
    public function getDistrictsHeatmap(): array
    {
        return $this->dashboardCache->territoryDistricts(function () {
            $breakdown = [];
            foreach (self::DISTRICTS as $slug => $data) {
                $stats = $this->getTerritorySummary($slug, null);
                $breakdown[$slug] = [
                    'name' => $data['short_name'],
                    'full_name' => $data['name'],
                    'chef_lieu' => $data['chef_lieu'],
                    'actors_count' => $stats['actors']['total_actors'],
                    'missions_count' => $stats['missions']['total'],
                    'volume_fcfa' => $stats['missions']['total_volume_fcfa'],
                    'realization_rate' => $stats['missions']['realization_rate'],
                    'dispute_rate' => $stats['missions']['dispute_rate'],
                ];
            }
            return $breakdown;
        });
    }

    /**
     * Ventilation pour coloration cartographique des Communes du Grand Abidjan.
     */
    public function getCommunesHeatmap(): array
    {
        return $this->dashboardCache->territoryCommunes(function () {
            $breakdown = [];
            foreach (self::ABIDJAN_COMMUNES as $slug => $data) {
                $stats = $this->getTerritorySummary('abidjan', $slug);
                $breakdown[$slug] = [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'actors_count' => $stats['actors']['total_actors'],
                    'artisans_count' => $stats['actors']['artisans'],
                    'fournisseurs_count' => $stats['actors']['fournisseurs'],
                    'livreurs_count' => $stats['actors']['livreurs'],
                    'clients_count' => $stats['actors']['clients'],
                    'missions_count' => $stats['missions']['total'],
                    'volume_fcfa' => $stats['missions']['total_volume_fcfa'],
                    'realization_rate' => $stats['missions']['realization_rate'],
                ];
            }
            return $breakdown;
        });
    }

    /**
     * Récupère la liste dynamique des entités (acteurs ou missions) de la zone sélectionnée.
     */
    public function getTerritoryEntities(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $tab = $filters['entity_type'] ?? 'all'; // 'all', 'artisans', 'clients', 'fournisseurs', 'livreurs', 'missions', 'litiges'
        $districtSlug = $filters['district'] ?? null;
        $communeSlug = $filters['commune'] ?? null;
        $search = trim($filters['search'] ?? '');

        $communeModel = null;
        if ($communeSlug) {
            $communeModel = Commune::where('slug', $communeSlug)->first();
        }

        if (in_array($tab, ['missions', 'litiges'])) {
            $query = Mission::with(['client:id,name,phone', 'artisan:id,name,phone,score_prosartisan'])
                ->orderByDesc('created_at');

            $this->applyMissionLocationScope($query, $districtSlug, $communeSlug, $communeModel);

            if ($tab === 'litiges') {
                $query->where('status', 'disputed');
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('location_address', 'like', "%{$search}%")
                        ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('artisan', fn($aq) => $aq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            }

            return $query->paginate($perPage)->through(function (Mission $mission) {
                return [
                    'id' => $mission->id,
                    'type' => $mission->status === 'disputed' ? 'litige' : 'mission',
                    'title' => "Mission #{$mission->id} — " . ($mission->category ?: 'Travaux'),
                    'subtitle' => $mission->description ? \Illuminate\Support\Str::limit($mission->description, 60) : 'Chantier',
                    'actor_name' => $mission->artisan?->name ?? 'Non assigné',
                    'client_name' => $mission->client?->name ?? 'Client',
                    'contact' => $mission->artisan?->phone ?? ($mission->client?->phone ?? '-'),
                    'status' => (string) $mission->status,
                    'montant' => (int) $mission->montant_total,
                    'amount_fcfa' => (int) $mission->montant_total,
                    'location' => $mission->location_address ?: 'Côte d\'Ivoire',
                    'action_url' => "/admin/missions?search_mission=" . $mission->id,
                    'created_at' => $mission->created_at?->toIso8601String(),
                ];
            });
        }

        // Requête sur les Utilisateurs (acteurs)
        $query = User::with('commune:id,name,slug')
            ->orderByDesc('created_at');

        $this->applyUserLocationScope($query, $districtSlug, $communeSlug, $communeModel);

        if ($tab === 'artisans') {
            $query->where('role', 'artisan');
        } elseif ($tab === 'clients') {
            $query->where('role', 'client');
        } elseif ($tab === 'fournisseurs') {
            $query->where('role', 'fournisseur');
        } elseif ($tab === 'livreurs') {
            $query->where('role', 'livreur');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('payment_phone', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->through(function (User $user) {
            return [
                'id' => $user->id,
                'type' => $user->role,
                'role' => $user->role,
                'name' => $user->name,
                'title' => $user->name,
                'subtitle' => $user->role ? ucfirst($user->role) . ($user->phone ? ' • ' . $user->phone : '') : 'Utilisateur',
                'phone' => $user->phone,
                'contact' => $user->phone,
                'status' => $user->kyc_status,
                'kyc_status' => $user->kyc_status,
                'score_prosartisan' => (int) $user->score_prosartisan,
                'commune' => $user->commune?->name ?? ($user->city ?? 'Abidjan'),
                'location' => $user->commune?->name ?? ($user->city ?? 'Abidjan'),
                'action_url' => "/admin/users?search_user=" . urlencode($user->phone ?: $user->name),
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Applique le filtre géographique sur la requête utilisateur.
     */
    private function applyUserLocationScope(Builder $query, ?string $districtSlug, ?string $communeSlug, ?Commune $communeModel): void
    {
        if ($communeModel) {
            $query->where('commune_id', $communeModel->id);
            return;
        }

        if ($communeSlug && isset(self::ABIDJAN_COMMUNES[$communeSlug])) {
            $communeName = self::ABIDJAN_COMMUNES[$communeSlug]['name'];
            $query->whereHas('commune', function ($cq) use ($communeSlug, $communeName) {
                $cq->where('slug', $communeSlug);
                $this->orWhereVilleMatch($cq, 'name', $communeName);
            });
            return;
        }

        if ($districtSlug && isset(self::DISTRICTS[$districtSlug])) {
            $villes = self::DISTRICTS[$districtSlug]['villes'];
            $query->whereHas('commune', function ($cq) use ($villes) {
                $cq->where(function ($sub) use ($villes) {
                    foreach ($villes as $v) {
                        $this->orWhereVilleMatch($sub, 'name', $v);
                        $this->orWhereVilleMatch($sub, 'city', $v);
                    }
                });
            });
        }
    }

    /**
     * Ajoute une condition `OR column = ville` bornée par un séparateur de mot
     * (espace, virgule, tiret, début/fin de chaîne) afin d'éviter qu'une ville
     * courte (ex: "Man", "Divo", "Bouna") ne matche par simple sous-chaîne une
     * adresse sans rapport (ex: "Allemagne", "Dividende"). N'utilise que LIKE,
     * portable identiquement en SQLite (tests) et MySQL (production).
     */
    private function orWhereVilleMatch(Builder $query, string $column, string $ville): void
    {
        $ville = trim($ville);
        if ($ville === '') {
            return;
        }

        $query->orWhere($column, $ville)
            ->orWhere($column, 'like', "{$ville} %")
            ->orWhere($column, 'like', "% {$ville}")
            ->orWhere($column, 'like', "% {$ville} %")
            ->orWhere($column, 'like', "{$ville},%")
            ->orWhere($column, 'like', "%, {$ville}")
            ->orWhere($column, 'like', "%,{$ville}%")
            ->orWhere($column, 'like', "{$ville}-%")
            ->orWhere($column, 'like', "%-{$ville}");
    }

    /**
     * Applique le filtre géographique sur la requête mission.
     */
    private function applyMissionLocationScope(Builder $query, ?string $districtSlug, ?string $communeSlug, ?Commune $communeModel): void
    {
        if ($communeModel) {
            $name = $communeModel->name;
            $query->where(function ($q) use ($name, $communeModel) {
                $this->orWhereVilleMatch($q, 'client_address', $name);
                $q->orWhereHas('client', fn($cq) => $cq->where('commune_id', $communeModel->id))
                    ->orWhereHas('artisan', fn($aq) => $aq->where('commune_id', $communeModel->id));
            });
            return;
        }

        if ($communeSlug && isset(self::ABIDJAN_COMMUNES[$communeSlug])) {
            $name = self::ABIDJAN_COMMUNES[$communeSlug]['name'];
            $query->where(function ($q) use ($name, $communeSlug) {
                $this->orWhereVilleMatch($q, 'client_address', $name);
                $q->orWhereHas('client', fn($cq) => $cq->whereHas('commune', fn($sub) => $sub->where('slug', $communeSlug)))
                    ->orWhereHas('artisan', fn($aq) => $aq->whereHas('commune', fn($sub) => $sub->where('slug', $communeSlug)));
            });
            return;
        }

        if ($districtSlug && isset(self::DISTRICTS[$districtSlug])) {
            $villes = self::DISTRICTS[$districtSlug]['villes'];
            $query->where(function ($q) use ($villes) {
                foreach ($villes as $v) {
                    $this->orWhereVilleMatch($q, 'client_address', $v);
                }
                $q->orWhereHas('client', function ($cq) use ($villes) {
                    $cq->whereHas('commune', function ($sub) use ($villes) {
                        $sub->where(function ($w) use ($villes) {
                            foreach ($villes as $v) {
                                $this->orWhereVilleMatch($w, 'name', $v);
                                $this->orWhereVilleMatch($w, 'city', $v);
                            }
                        });
                    });
                });
            });
        }
    }
}
