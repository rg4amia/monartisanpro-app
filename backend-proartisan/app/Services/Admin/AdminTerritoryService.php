<?php

namespace App\Services\Admin;

use App\Models\Commune;
use App\Models\Evaluation;
use App\Models\FournisseurAgree;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use App\States\Mission\MissionState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

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
            'name' => 'District Autonome de Yamoussoukro',
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

    /** Acteurs affichables sur la carte et dans le tableau. */
    public const ACTOR_TYPES = ['client', 'artisan', 'livreur', 'fournisseur'];

    /** Filtre de type du tableau détaillé. `all` = tous les acteurs cochés. */
    public const ENTITY_TYPES = ['all', 'client', 'artisan', 'livreur', 'fournisseur', 'mission', 'litige'];

    public const MISSION_STATUS_FILTERS = ['all', 'en_cours', 'terminees', 'litige'];

    public const KYC_FILTERS = ['all', 'actif', 'en_attente', 'rejete'];

    /** Période des missions, en jours ; `all` = sans limite. */
    public const PERIOD_FILTERS = ['all', '30', '90'];

    public const SORTS = ['recent', 'name', 'score', 'montant'];

    /** Pseudo-zone des acteurs et missions qu'aucune commune ne rattache à un district. */
    public const UNLOCATED = 'non_renseigne';

    public const UNLOCATED_LABEL = 'Commune non renseignée';

    private const NATIONAL_LABEL = "Toute la Côte d'Ivoire (Vue Nationale)";

    /** Missions comptées « en cours » : financées ou en chantier. */
    private const MISSION_ACTIVE_STATUSES = ['funded_locked', 'in_progress'];

    /** Courses qui occupent un livreur. */
    private const DELIVERY_ACTIVE_STATUSES = ['driver_assigned', 'driver_picked_up'];

    private const KYC_LABELS = [
        'en_attente' => 'En attente',
        'actif' => 'Actif',
        'rejete' => 'Rejeté',
        'suspendu' => 'Suspendu',
    ];

    private const SUPPLIER_STATUS_LABELS = [
        'en_attente' => 'Agrément en attente',
        'agree' => 'Agréée',
        'suspendu' => 'Agrément suspendu',
    ];

    private const ROLE_LABELS = [
        'client' => 'Client',
        'artisan' => 'Artisan',
        'livreur' => 'Livreur',
        'fournisseur' => 'Quincaillerie',
    ];

    /** Anciens identifiants pluriels, encore présents dans des liens enregistrés. */
    private const ENTITY_TYPE_ALIASES = [
        'clients' => 'client',
        'artisans' => 'artisan',
        'livreurs' => 'livreur',
        'fournisseurs' => 'fournisseur',
        'missions' => 'mission',
        'litiges' => 'litige',
    ];

    /** @var array<string, array{0: ?string, 1: ?string}> Mémo des adresses déjà situées. */
    private array $locatedTexts = [];

    /** @var array<string, string>|null Motifs de recherche des communes d'Abidjan. */
    private ?array $communePatterns = null;

    /** @var array<string, list<string>>|null Motifs de recherche des villes de chaque district. */
    private ?array $districtPatterns = null;

    // ─────────────────────────────────────────────────────────────────────
    // Filtres
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ramène les filtres reçus à des valeurs connues. Une valeur inconnue
     * retombe sur « tout » : la validation stricte est faite par le contrôleur.
     *
     * @param  array<string, mixed>  $input
     * @return array{district: ?string, commune: ?string, entity_type: string, types: list<string>, mission_status: string, kyc: string, period: string, search: string, sort: string}
     */
    public function normalizeFilters(array $input): array
    {
        $entityType = (string) ($input['entity_type'] ?? 'all');
        $entityType = self::ENTITY_TYPE_ALIASES[$entityType] ?? $entityType;

        $types = $input['types'] ?? [];
        if (is_string($types)) {
            $types = array_filter(explode(',', $types));
        }
        $types = array_values(array_intersect([...self::ACTOR_TYPES, 'mission'], (array) $types));

        $district = $input['district'] ?? null;
        $commune = $input['commune'] ?? null;

        return [
            'district' => $district === self::UNLOCATED || isset(self::DISTRICTS[$district]) ? $district : null,
            'commune' => isset(self::ABIDJAN_COMMUNES[$commune]) ? $commune : null,
            'entity_type' => in_array($entityType, self::ENTITY_TYPES, true) ? $entityType : 'all',
            'types' => $types,
            'mission_status' => $this->oneOf($input['mission_status'] ?? null, self::MISSION_STATUS_FILTERS, 'all'),
            'kyc' => $this->oneOf($input['kyc'] ?? null, self::KYC_FILTERS, 'all'),
            'period' => $this->oneOf($input['period'] ?? null, self::PERIOD_FILTERS, 'all'),
            'search' => trim((string) ($input['search'] ?? '')),
            'sort' => $this->oneOf($input['sort'] ?? null, self::SORTS, 'recent'),
        ];
    }

    /**
     * Valeurs acceptées pour `entity_type`, anciens identifiants pluriels compris.
     *
     * @return list<string>
     */
    public static function acceptedEntityTypes(): array
    {
        return [...self::ENTITY_TYPES, ...array_keys(self::ENTITY_TYPE_ALIASES)];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $default): string
    {
        return in_array((string) $value, $allowed, true) ? (string) $value : $default;
    }

    /**
     * Vrai si les filtres ne restreignent ni les acteurs ni les missions
     * comptés : le résultat mis en cache peut alors être servi.
     */
    private function countsEverything(array $filters): bool
    {
        return ($filters['mission_status'] ?? 'all') === 'all'
            && ($filters['kyc'] ?? 'all') === 'all'
            && ($filters['period'] ?? 'all') === 'all';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Matrice par zone
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Effectifs par type et par zone : une ligne par district, une par commune
     * du Grand Abidjan, une ligne « Commune non renseignée » et le total
     * national. Chaque acteur et chaque mission n'est compté que dans une
     * seule zone d'un même niveau, si bien que la somme des districts et des
     * non renseignés retrouve le total national.
     *
     * @param  array<string, mixed>  $filters
     * @return array{national: array<string, mixed>, unlocated: array<string, mixed>, districts: array<string, array<string, mixed>>, communes: array<string, array<string, mixed>>}
     */
    public function getZoneMatrix(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        return $this->countsEverything($filters)
            ? $this->snapshot()['rows']
            : $this->buildSnapshot($filters)['rows'];
    }

    /**
     * Instantané sans filtre, mis en cache : la matrice et, pour chaque zone,
     * les communes et les missions qui lui sont rattachées.
     */
    private function snapshot(): array
    {
        return $this->dashboardCache->territorySnapshot(
            fn () => $this->buildSnapshot($this->normalizeFilters([]))
        );
    }

    private function buildSnapshot(array $filters): array
    {
        $rows = [
            'national' => $this->emptyRow('all', 'Côte d\'Ivoire', 'national'),
            'unlocated' => $this->emptyRow(self::UNLOCATED, self::UNLOCATED_LABEL, 'unlocated'),
            'districts' => [],
            'communes' => [],
        ];
        foreach (self::DISTRICTS as $slug => $data) {
            $rows['districts'][$slug] = $this->emptyRow($slug, $data['short_name'], 'district');
        }
        foreach (self::ABIDJAN_COMMUNES as $slug => $data) {
            $rows['communes'][$slug] = $this->emptyRow($slug, $data['name'], 'commune');
        }

        // Rattachement de chaque commune enregistrée à un district et, dans le
        // Grand Abidjan, à l'une des 13 communes du référentiel.
        $communeIds = ['districts' => [], 'communes' => [], 'unmapped' => []];
        $communeZones = [];
        foreach (Commune::query()->get(['id', 'name', 'slug', 'city']) as $commune) {
            $zone = $this->locateCommune($commune);
            $communeZones[$commune->id] = $zone;

            if ($zone[0] === null) {
                $communeIds['unmapped'][] = $commune->id;

                continue;
            }
            $communeIds['districts'][$zone[0]][] = $commune->id;
            if ($zone[1] !== null) {
                $communeIds['communes'][$zone[1]][] = $commune->id;
            }
        }

        $zoneOfCommune = fn ($communeId): array => $communeZones[$communeId] ?? [null, null];
        $add = function (array $zone, string $field, int $amount) use (&$rows): void {
            $rows['national'][$field] += $amount;
            if ($zone[0] === null) {
                $rows['unlocated'][$field] += $amount;

                return;
            }
            $rows['districts'][$zone[0]][$field] += $amount;
            if ($zone[1] !== null) {
                $rows['communes'][$zone[1]][$field] += $amount;
            }
        };

        $kyc = $filters['kyc'];
        $actors = fn () => User::query()
            ->whereIn('role', self::ACTOR_TYPES)
            ->when($kyc !== 'all', fn (Builder $q) => $q->where('kyc_status', $kyc));

        // Acteurs par commune, rôle et statut KYC.
        $fields = ['client' => 'clients', 'artisan' => 'artisans', 'livreur' => 'livreurs', 'fournisseur' => 'fournisseurs'];
        $actorCounts = $actors()
            ->toBase()
            ->select('commune_id', 'role', 'kyc_status', DB::raw('COUNT(*) as total'))
            ->groupBy('commune_id', 'role', 'kyc_status')
            ->get();
        foreach ($actorCounts as $count) {
            $zone = $zoneOfCommune($count->commune_id);
            $add($zone, $fields[$count->role], (int) $count->total);
            if ($count->role === 'artisan' && $count->kyc_status === 'actif') {
                $add($zone, 'artisans_kyc_actif', (int) $count->total);
            }
        }

        // Quincailleries dont l'agrément est validé.
        $agreedIds = FournisseurAgree::query()->where('statut', 'agree')->pluck('user_id');
        $this->countActorsByCommune($actors()->where('role', 'fournisseur')->whereIn('id', $agreedIds), $zoneOfCommune, $add, 'fournisseurs_agrees');

        // Livreurs occupés par une course.
        $busyDriverIds = $this->busyDriverIds();
        $this->countActorsByCommune($actors()->where('role', 'livreur')->whereIn('id', $busyDriverIds), $zoneOfCommune, $add, 'livreurs_en_course');

        // Clients ayant une mission en cours.
        $activeClientIds = Mission::query()->toBase()
            ->whereIn('status', self::MISSION_ACTIVE_STATUSES)
            ->distinct()
            ->pluck('client_id');
        $this->countActorsByCommune($actors()->where('role', 'client')->whereIn('id', $activeClientIds), $zoneOfCommune, $add, 'clients_mission_active');

        // Missions : situées par l'adresse du chantier, à défaut par la commune
        // du client, puis par celle de l'artisan.
        $userCommunes = User::withTrashed()->toBase()->whereNotNull('commune_id')->pluck('commune_id', 'id');
        $missionIds = ['districts' => [], 'communes' => [], 'unlocated' => []];

        $missions = $this->applyMissionFilters(Mission::query()->toBase(), $filters)
            ->select('id', 'status', 'montant_total', 'client_address', 'client_id', 'artisan_id')
            ->orderBy('id')
            ->cursor();

        foreach ($missions as $mission) {
            $zone = $this->locateText((string) $mission->client_address);
            if ($zone[0] === null) {
                $zone = $zoneOfCommune($userCommunes[$mission->client_id] ?? null);
            }
            if ($zone[0] === null) {
                $zone = $zoneOfCommune($userCommunes[$mission->artisan_id] ?? null);
            }

            if ($zone[0] === null) {
                $missionIds['unlocated'][] = $mission->id;
            } else {
                $missionIds['districts'][$zone[0]][] = $mission->id;
                if ($zone[1] !== null) {
                    $missionIds['communes'][$zone[1]][] = $mission->id;
                }
            }

            $add($zone, 'missions_total', 1);
            $add($zone, 'volume_fcfa', (int) $mission->montant_total);
            if (in_array($mission->status, self::MISSION_ACTIVE_STATUSES, true)) {
                $add($zone, 'missions_en_cours', 1);
            } elseif ($mission->status === 'completed') {
                $add($zone, 'missions_terminees', 1);
            } elseif ($mission->status === 'disputed') {
                $add($zone, 'litiges', 1);
            }
        }

        $rows['national'] = $this->finishRow($rows['national']);
        $rows['unlocated'] = $this->finishRow($rows['unlocated']);
        $rows['districts'] = array_map($this->finishRow(...), $rows['districts']);
        $rows['communes'] = array_map($this->finishRow(...), $rows['communes']);

        return ['rows' => $rows, 'commune_ids' => $communeIds, 'mission_ids' => $missionIds];
    }

    private function emptyRow(string $slug, string $name, string $type): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'type' => $type,
            'clients' => 0,
            'clients_mission_active' => 0,
            'artisans' => 0,
            'artisans_kyc_actif' => 0,
            'livreurs' => 0,
            'livreurs_en_course' => 0,
            'fournisseurs' => 0,
            'fournisseurs_agrees' => 0,
            'missions_total' => 0,
            'missions_en_cours' => 0,
            'missions_terminees' => 0,
            'litiges' => 0,
            'volume_fcfa' => 0,
        ];
    }

    private function finishRow(array $row): array
    {
        $total = $row['missions_total'];
        $row['total_actors'] = $row['clients'] + $row['artisans'] + $row['livreurs'] + $row['fournisseurs'];
        $row['realization_rate'] = $total > 0 ? round(($row['missions_terminees'] / $total) * 100, 1) : 0;
        $row['dispute_rate'] = $total > 0 ? round(($row['litiges'] / $total) * 100, 1) : 0;

        return $row;
    }

    private function countActorsByCommune(Builder $query, \Closure $zoneOfCommune, \Closure $add, string $field): void
    {
        $counts = $query->toBase()
            ->select('commune_id', DB::raw('COUNT(*) as total'))
            ->groupBy('commune_id')
            ->get();

        foreach ($counts as $count) {
            $add($zoneOfCommune($count->commune_id), $field, (int) $count->total);
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function busyDriverIds(): Collection
    {
        return Order::query()->toBase()
            ->whereIn('status', self::DELIVERY_ACTIVE_STATUSES)
            ->whereNotNull('driver_id')
            ->distinct()
            ->pluck('driver_id');
    }

    /**
     * Filtres de statut et de période, communs à la matrice et au tableau.
     *
     * @template TQuery of Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function applyMissionFilters($query, array $filters)
    {
        match ($filters['mission_status'] ?? 'all') {
            'en_cours' => $query->whereIn('status', self::MISSION_ACTIVE_STATUSES),
            'terminees' => $query->where('status', 'completed'),
            'litige' => $query->where('status', 'disputed'),
            default => null,
        };

        if (($filters['period'] ?? 'all') !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int) $filters['period']));
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Rattachement géographique
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array{0: ?string, 1: ?string} District et commune d'Abidjan, ou nuls.
     */
    private function locateCommune(Commune $commune): array
    {
        $slug = str_replace('-', '_', (string) $commune->slug);
        if (isset(self::ABIDJAN_COMMUNES[$slug])) {
            return ['abidjan', $slug];
        }

        $zone = $this->locateText((string) $commune->name);

        return $zone[0] !== null ? $zone : $this->locateText((string) $commune->city);
    }

    /**
     * Situe un texte libre (adresse, nom de commune) : une ville n'est reconnue
     * que bornée par un séparateur de mot, afin qu'une ville courte (« Man »,
     * « Divo », « Bouna ») ne soit pas trouvée dans un mot sans rapport
     * (« Allemagne », « Dividende »).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function locateText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [null, null];
        }
        if (isset($this->locatedTexts[$text])) {
            return $this->locatedTexts[$text];
        }

        $this->communePatterns ??= array_map(fn (array $data) => $this->wordPattern($data['name']), self::ABIDJAN_COMMUNES);
        $this->districtPatterns ??= array_map(
            fn (array $data) => array_map($this->wordPattern(...), $data['villes']),
            self::DISTRICTS,
        );

        $haystack = $this->normalizeText($text);
        $zone = [null, null];

        foreach ($this->communePatterns as $slug => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                $zone = ['abidjan', $slug];
                break;
            }
        }

        if ($zone[0] === null) {
            foreach ($this->districtPatterns as $slug => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $haystack) === 1) {
                        $zone = [$slug, null];
                        break 2;
                    }
                }
            }
        }

        return $this->locatedTexts[$text] = $zone;
    }

    private function normalizeText(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function wordPattern(string $ville): string
    {
        return '/(?<![a-z0-9])'.preg_quote($this->normalizeText($ville), '/').'(?![a-z0-9])/';
    }

    /**
     * Zone demandée et identifiants qui lui sont rattachés. `commune_ids` et
     * `mission_ids` valent `null` pour la vue nationale (aucune restriction).
     *
     * @return array{type: string, slug: string, name: string, district: ?string, commune: ?string, commune_ids: ?list<int>, unmapped_ids: list<int>, mission_ids: ?list<int>}
     */
    private function zoneScope(?string $district, ?string $commune): array
    {
        $snapshot = $this->snapshot();

        if ($commune && isset(self::ABIDJAN_COMMUNES[$commune])) {
            return [
                'type' => 'commune', 'slug' => $commune, 'district' => 'abidjan', 'commune' => $commune,
                'name' => 'Commune de '.self::ABIDJAN_COMMUNES[$commune]['name'].' (Grand Abidjan)',
                'commune_ids' => $snapshot['commune_ids']['communes'][$commune] ?? [],
                'unmapped_ids' => [],
                'mission_ids' => $snapshot['mission_ids']['communes'][$commune] ?? [],
            ];
        }

        if ($district === self::UNLOCATED) {
            return [
                'type' => 'unlocated', 'slug' => self::UNLOCATED, 'district' => self::UNLOCATED, 'commune' => null,
                'name' => self::UNLOCATED_LABEL,
                'commune_ids' => [],
                'unmapped_ids' => $snapshot['commune_ids']['unmapped'],
                'mission_ids' => $snapshot['mission_ids']['unlocated'],
            ];
        }

        if ($district && isset(self::DISTRICTS[$district])) {
            return [
                'type' => 'district', 'slug' => $district, 'district' => $district, 'commune' => null,
                'name' => self::DISTRICTS[$district]['name'],
                'commune_ids' => $snapshot['commune_ids']['districts'][$district] ?? [],
                'unmapped_ids' => [],
                'mission_ids' => $snapshot['mission_ids']['districts'][$district] ?? [],
            ];
        }

        return [
            'type' => 'national', 'slug' => 'all', 'district' => null, 'commune' => null,
            'name' => self::NATIONAL_LABEL,
            'commune_ids' => null, 'unmapped_ids' => [], 'mission_ids' => null,
        ];
    }

    private function applyUserZone(Builder $query, array $zone): void
    {
        if ($zone['type'] === 'national') {
            return;
        }

        if ($zone['type'] === 'unlocated') {
            $query->where(fn (Builder $q) => $q
                ->whereNull('users.commune_id')
                ->orWhereIn('users.commune_id', $zone['unmapped_ids']));

            return;
        }

        $query->whereIn('users.commune_id', $zone['commune_ids']);
    }

    private function applyMissionZone(Builder $query, array $zone): void
    {
        if ($zone['mission_ids'] !== null) {
            $query->whereIn('missions.id', $zone['mission_ids']);
        }
    }

    private function zoneRow(array $rows, array $zone): array
    {
        return match ($zone['type']) {
            'commune' => $rows['communes'][$zone['slug']],
            'district' => $rows['districts'][$zone['slug']],
            'unlocated' => $rows['unlocated'],
            default => $rows['national'],
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Synthèse et ventilations d'une zone
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Synthèse d'une zone (nationale, district, commune ou « non renseignée »).
     *
     * @param  array<string, mixed>  $filters
     */
    public function getTerritorySummary(?string $districtSlug = null, ?string $communeSlug = null, array $filters = []): array
    {
        $zone = $this->zoneScope($districtSlug, $communeSlug);
        $row = $this->zoneRow($this->getZoneMatrix($filters), $zone);

        $reviews = Evaluation::query();
        if ($zone['mission_ids'] !== null) {
            $reviews->whereIn('mission_id', $zone['mission_ids']);
        }
        $totalReviews = (clone $reviews)->count();

        $artisans = User::query()->where('role', 'artisan');
        $this->applyUserZone($artisans, $zone);

        return [
            'zone' => [
                'type' => $zone['type'],
                'slug' => $zone['slug'],
                'name' => $zone['name'],
                'district_slug' => $zone['district'],
                'commune_slug' => $zone['commune'],
            ],
            'actors' => [
                'clients' => $row['clients'],
                'clients_with_active_missions' => $row['clients_mission_active'],
                'artisans' => $row['artisans'],
                'artisans_kyc_actif' => $row['artisans_kyc_actif'],
                'artisans_kyc_percent' => $row['artisans'] > 0 ? round(($row['artisans_kyc_actif'] / $row['artisans']) * 100, 1) : 0,
                'fournisseurs' => $row['fournisseurs'],
                'fournisseurs_agrees' => $row['fournisseurs_agrees'],
                'livreurs' => $row['livreurs'],
                'livreurs_en_course' => $row['livreurs_en_course'],
                'total_actors' => $row['total_actors'],
            ],
            'missions' => [
                'total' => $row['missions_total'],
                'en_cours' => $row['missions_en_cours'],
                'completed' => $row['missions_terminees'],
                'terminee' => $row['missions_terminees'],
                'disputed' => $row['litiges'],
                'litige' => $row['litiges'],
                'realization_rate' => $row['realization_rate'],
                'dispute_rate' => $row['dispute_rate'],
                'total_volume_fcfa' => $row['volume_fcfa'],
                'financial_volume_fcfa' => $row['volume_fcfa'],
            ],
            'reputation' => [
                // Sans aucun avis, la note vaut null et s'affiche « Non évalué » (Règle d'or 29).
                'avg_rating' => $totalReviews > 0 ? round((float) (clone $reviews)->avg('note'), 1) : null,
                'avg_score_prosartisan' => (int) round((clone $artisans)->avg('score_prosartisan') ?: 0),
                'total_reviews' => $totalReviews,
            ],
        ];
    }

    /**
     * Ventilations complémentaires d'une zone : artisans par catégorie de
     * métier, fournisseurs par secteur d'activité, et conformité CNMCI des
     * artisans. Affichées dans le détail de zone de Cartographie & Territoires.
     */
    public function getTerritoryBreakdowns(?string $districtSlug = null, ?string $communeSlug = null): array
    {
        $zone = $this->zoneScope($districtSlug, $communeSlug);

        return [
            'artisan_categories' => $this->getArtisanCategoryBreakdown($zone),
            'supplier_sectors' => $this->getSupplierSectorBreakdown($zone),
            'cnmci' => $this->getCnmciBreakdown($zone),
        ];
    }

    /**
     * Répartition des artisans inscrits dans la zone par secteur de métier
     * (`sectors`, via `artisan_profiles`). Les artisans sans profil ou sans
     * secteur choisi sont regroupés sous « Non renseigné » plutôt qu'omis :
     * une catégorie manquante est une donnée en soi, pas une absence à cacher.
     */
    private function getArtisanCategoryBreakdown(array $zone): array
    {
        $query = User::query()->where('role', 'artisan');
        $this->applyUserZone($query, $zone);

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->leftJoin('artisan_profiles', 'artisan_profiles.user_id', '=', 'users.id')
            ->leftJoin('sectors', 'sectors.id', '=', 'artisan_profiles.sector_id')
            ->select('sectors.id as sector_id', 'sectors.name as sector_name', 'sectors.icon as sector_icon', DB::raw('COUNT(users.id) as total'))
            ->groupBy('sectors.id', 'sectors.name', 'sectors.icon')
            ->orderByDesc('total')
            ->get();

        return $this->formatBreakdown($rows, $total);
    }

    /**
     * Répartition des fournisseurs (quincailleries) de la zone par secteur
     * d'activité (`fournisseurs_agrees.sector_id`, assigné depuis la fiche
     * utilisateur du backoffice). Même convention « Non renseigné » que
     * ci-dessus pour les boutiques sans secteur encore assigné.
     */
    private function getSupplierSectorBreakdown(array $zone): array
    {
        $userQuery = User::query()->where('role', 'fournisseur');
        $this->applyUserZone($userQuery, $zone);
        $userIds = (clone $userQuery)->pluck('id');

        if ($userIds->isEmpty()) {
            return ['total' => 0, 'items' => []];
        }

        $rows = FournisseurAgree::query()
            ->whereIn('user_id', $userIds)
            ->leftJoin('sectors', 'sectors.id', '=', 'fournisseurs_agrees.sector_id')
            ->select('sectors.id as sector_id', 'sectors.name as sector_name', 'sectors.icon as sector_icon', DB::raw('COUNT(fournisseurs_agrees.id) as total'))
            ->groupBy('sectors.id', 'sectors.name', 'sectors.icon')
            ->orderByDesc('total')
            ->get();

        return $this->formatBreakdown($rows, $userIds->count());
    }

    /**
     * @param  Collection<int, object{sector_id: ?int, sector_name: ?string, sector_icon: ?string, total: int}>  $rows
     */
    private function formatBreakdown($rows, int $total): array
    {
        return [
            'total' => $total,
            'items' => $rows->map(fn ($row) => [
                'sector_id' => $row->sector_id,
                'label' => $row->sector_name ?? 'Non renseigné',
                'icon' => $row->sector_icon,
                'count' => (int) $row->total,
                'percent' => $total > 0 ? round(($row->total / $total) * 100, 1) : 0.0,
            ])->values()->all(),
        ];
    }

    /**
     * Conformité CNMCI des artisans de la zone : combien détiennent une carte
     * validée, combien sont en attente / rejetés / n'ont jamais renseigné.
     */
    private function getCnmciBreakdown(array $zone): array
    {
        $query = User::query()->where('role', 'artisan');
        $this->applyUserZone($query, $zone);

        $counts = (clone $query)
            ->select('cnmci_status', DB::raw('COUNT(*) as total'))
            ->groupBy('cnmci_status')
            ->pluck('total', 'cnmci_status')
            ->toArray();

        $total = (int) array_sum($counts);
        $valide = (int) ($counts['valide'] ?? 0);

        return [
            'total_artisans' => $total,
            'valide' => $valide,
            'en_attente' => (int) ($counts['en_attente'] ?? 0),
            'rejete' => (int) ($counts['rejete'] ?? 0),
            'non_renseigne' => (int) ($counts['non_renseigne'] ?? 0),
            'valide_percent' => $total > 0 ? round(($valide / $total) * 100, 1) : 0.0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tableau détaillé
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Liste paginée des acteurs ou des missions de la zone sélectionnée.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getTerritoryEntities(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        [$query, $mapRow] = $this->entityQuery($this->normalizeFilters($filters));

        return $query->paginate(max(1, min($perPage, 100)))->through($mapRow);
    }

    /**
     * @return array{0: Builder, 1: \Closure}
     */
    private function entityQuery(array $filters): array
    {
        $zone = $this->zoneScope($filters['district'], $filters['commune']);

        return in_array($filters['entity_type'], ['mission', 'litige'], true)
            ? $this->missionEntityQuery($filters, $zone)
            : $this->actorEntityQuery($filters, $zone);
    }

    private function missionEntityQuery(array $filters, array $zone): array
    {
        $query = Mission::query()->with(['client:id,name,phone', 'artisan:id,name,phone']);

        $this->applyMissionZone($query, $zone);
        $this->applyMissionFilters($query, $filters);

        if ($filters['entity_type'] === 'litige') {
            $query->where('status', 'disputed');
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('gemini_category', 'like', "%{$search}%")
                    ->orWhere('client_address', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('artisan', fn ($aq) => $aq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                if (ctype_digit($search)) {
                    $q->orWhere('missions.id', (int) $search);
                }
            });
        }

        $filters['sort'] === 'montant'
            ? $query->orderByDesc('montant_total')->orderByDesc('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');

        $mapRow = function (Mission $mission): array {
            $status = (string) $mission->status;

            return [
                'id' => $mission->id,
                'type' => $status === 'disputed' ? 'litige' : 'mission',
                'title' => "Mission #{$mission->id}".($mission->gemini_category ? ' — '.$mission->gemini_category : ''),
                'subtitle' => $mission->description ? Str::limit($mission->description, 60) : null,
                'actor_name' => $mission->artisan?->name,
                'client_name' => $mission->client?->name,
                'contact' => $mission->client?->phone,
                'status' => $status,
                'status_label' => MissionState::labelFor($status),
                'amount_fcfa' => (int) $mission->montant_total,
                'location' => $mission->client_address ?: null,
                'action_url' => '/admin/missions?search_mission='.$mission->id,
                'created_at' => $mission->created_at?->toIso8601String(),
            ];
        };

        return [$query, $mapRow];
    }

    private function actorEntityQuery(array $filters, array $zone): array
    {
        $roles = $filters['entity_type'] !== 'all'
            ? [$filters['entity_type']]
            : (array_values(array_intersect(self::ACTOR_TYPES, $filters['types'])) ?: self::ACTOR_TYPES);

        $query = User::query()
            ->with(['commune:id,name,slug,city', 'artisanProfile.sector:id,name', 'fournisseurAgree'])
            ->whereIn('role', $roles);

        $this->applyUserZone($query, $zone);

        if ($filters['kyc'] !== 'all') {
            $query->where('kyc_status', $filters['kyc']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('payment_phone', 'like', "%{$search}%");
            });
        }

        match ($filters['sort']) {
            'name' => $query->orderBy('name')->orderBy('id'),
            'score' => $query->orderByDesc('score_prosartisan')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $busyDrivers = $this->busyDriverIds()->flip();

        $mapRow = function (User $user) use ($busyDrivers): array {
            $zone = $user->commune ? $this->locateCommune($user->commune) : [null, null];

            return [
                'id' => $user->id,
                'type' => $user->role,
                'title' => $user->name,
                'subtitle' => self::ROLE_LABELS[$user->role] ?? $user->role,
                'detail' => $this->actorDetail($user, $busyDrivers->has($user->id)),
                'contact' => $user->phone,
                'status' => $user->kyc_status,
                'status_label' => 'KYC '.Str::lower(self::KYC_LABELS[$user->kyc_status] ?? (string) $user->kyc_status),
                'score_prosartisan' => $user->role === 'artisan' ? (int) $user->score_prosartisan : null,
                // Jamais de commune supposée : sans commune, l'acteur est « non renseigné ».
                'location' => $user->commune?->name,
                'district' => $zone[0] !== null ? self::DISTRICTS[$zone[0]]['short_name'] : null,
                'action_url' => '/admin/users?search_users='.urlencode($user->phone ?: (string) $user->name),
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        };

        return [$query, $mapRow];
    }

    /**
     * Précision propre au type d'acteur : métier, boutique et agrément, course en cours.
     */
    private function actorDetail(User $user, bool $busy): ?string
    {
        return match ($user->role) {
            'artisan' => $user->artisanProfile?->sector?->name,
            'fournisseur' => $user->fournisseurAgree
                ? trim($user->fournisseurAgree->nom_boutique.' — '.(self::SUPPLIER_STATUS_LABELS[$user->fournisseurAgree->statut] ?? $user->fournisseurAgree->statut), ' —')
                : 'Fiche boutique non créée',
            'livreur' => $busy ? 'En course' : null,
            default => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Synthèse par zone et exports
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Lignes du tableau « Synthèse par zone » : les communes dans la vue Grand
     * Abidjan, sinon les districts suivis de la ligne « non renseignée ».
     *
     * @param  array<string, mixed>  $filters
     * @return array{level: string, rows: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public function getZoneSynthesis(array $filters = [], string $level = 'districts'): array
    {
        $matrix = $this->getZoneMatrix($filters);

        if ($level === 'communes') {
            return [
                'level' => 'communes',
                'rows' => array_values($matrix['communes']),
                'total' => array_merge($matrix['districts']['abidjan'], ['name' => 'Total Grand Abidjan']),
            ];
        }

        return [
            'level' => 'districts',
            'rows' => [...array_values($matrix['districts']), $matrix['unlocated']],
            'total' => array_merge($matrix['national'], ['name' => 'Total national']),
        ];
    }

    /**
     * En-têtes et lignes de l'export CSV de la synthèse par zone.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function synthesisExport(array $filters = [], string $level = 'districts'): array
    {
        $synthesis = $this->getZoneSynthesis($filters, $level);

        $line = fn (array $row): array => [
            $row['name'], $row['clients'], $row['artisans'], $row['artisans_kyc_actif'], $row['livreurs'], $row['livreurs_en_course'],
            $row['fournisseurs'], $row['fournisseurs_agrees'], $row['missions_en_cours'], $row['missions_terminees'],
            $row['litiges'], $row['missions_total'], $row['volume_fcfa'], $row['realization_rate'],
        ];

        return [
            [
                'Zone', 'Clients', 'Artisans', 'Artisans KYC actif', 'Livreurs', 'Livreurs en course',
                'Quincailleries', 'Quincailleries agréées', 'Missions en cours', 'Missions terminées',
                'Litiges', 'Missions (total)', 'Volume (FCFA)', 'Taux de réalisation (%)',
            ],
            [...array_map($line, $synthesis['rows']), $line($synthesis['total'])],
        ];
    }

    /**
     * En-têtes et lignes (chargées à la demande) de l'export CSV du tableau détaillé.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: LazyCollection<int, list<mixed>>, 2: int}
     */
    public function detailExport(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        [$query, $mapRow] = $this->entityQuery($filters);
        $count = (clone $query)->count();

        if (in_array($filters['entity_type'], ['mission', 'litige'], true)) {
            return [
                ['ID', 'Mission', 'Statut', 'Montant (FCFA)', 'Client', 'Artisan', 'Adresse du chantier', 'Créée le'],
                $query->lazy(500)->map(function (Mission $mission) use ($mapRow): array {
                    $row = $mapRow($mission);

                    return [
                        $row['id'], $row['title'], $row['status_label'], $row['amount_fcfa'], $row['client_name'],
                        $row['actor_name'], $row['location'], $mission->created_at?->format('Y-m-d H:i'),
                    ];
                }),
                $count,
            ];
        }

        return [
            ['ID', 'Type', 'Nom', 'Téléphone', 'Commune', 'District', 'Statut KYC', 'Score ProsArtisan', 'Précision', 'Inscrit le'],
            $query->lazy(500)->map(function (User $user) use ($mapRow): array {
                $row = $mapRow($user);

                return [
                    $row['id'], $row['subtitle'], $row['title'], $row['contact'], $row['location'], $row['district'],
                    self::KYC_LABELS[$user->kyc_status] ?? $user->kyc_status, $row['score_prosartisan'], $row['detail'],
                    $user->created_at?->format('Y-m-d H:i'),
                ];
            }),
            $count,
        ];
    }
}
