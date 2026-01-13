<?php

// ============================================================================
// composer.json - Dépendances Laravel
// ============================================================================

/*
{
    "name": "prosartisan/backend",
    "type": "project",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "inertiajs/inertia-laravel": "^1.0",
        "spatie/laravel-permission": "^6.0",
        "predis/predis": "^2.2",
        "intervention/image": "^3.0",
        "laravel/cashier": "^15.0",
        "guzzlehttp/guzzle": "^7.8"
    },
    "require-dev": {
        "laravel/pint": "^1.13",
        "phpunit/phpunit": "^10.5"
    }
}
*/

// ============================================================================
// config/inertia.php
// ============================================================================

return [
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/Pages'),
        ],
        'page_extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
        ],
    ],
];

// ============================================================================
// app/Domain/Identity/Models/User.php - Modèle de base utilisateur
// ============================================================================

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

abstract class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'nom',
        'prenoms',
        'email',
        'telephone',
        'password',
        'avatar',
        'statut',
        'email_verified_at',
        'telephone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'telephone_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    abstract public function getType(): string;
}

// ============================================================================
// app/Domain/Identity/Models/Artisan/Artisan.php
// ============================================================================

namespace App\Domain\Identity\Models\Artisan;

use App\Domain\Identity\Models\User;
use App\Domain\Reputation\Models\ScoreNZassa;
use App\Domain\Marketplace\Models\Mission;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Artisan extends User
{
    protected $table = 'artisans';

    protected $fillable = [
        'nom',
        'prenoms',
        'email',
        'telephone',
        'password',
        'avatar',
        'categorie_id',
        'specialites',
        'annees_experience',
        'certifications',
        'rayon_intervention_km',
        'latitude',
        'longitude',
        'adresse',
        'commune',
        'ville',
        'kyc_status',
        'kyc_documents',
        'statut',
    ];

    protected $casts = [
        'specialites' => 'array',
        'certifications' => 'array',
        'kyc_documents' => 'array',
        'annees_experience' => 'integer',
        'rayon_intervention_km' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function getType(): string
    {
        return 'artisan';
    }

    public function scoreNZassa(): HasOne
    {
        return $this->hasOne(ScoreNZassa::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    // Scope pour recherche par proximité
    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 50)
    {
        $haversine = "(6371 * acos(cos(radians(?)) 
                     * cos(radians(latitude)) 
                     * cos(radians(longitude) - radians(?)) 
                     + sin(radians(?)) 
                     * sin(radians(latitude))))";

        return $query
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereRaw("{$haversine} < ?", [$latitude, $longitude, $latitude, $radiusKm])
            ->orderBy('distance');
    }
}

// ============================================================================
// app/Domain/Identity/Models/Client/Client.php
// ============================================================================

namespace App\Domain\Identity\Models\Client;

use App\Domain\Identity\Models\User;
use App\Domain\Marketplace\Models\Mission;

class Client extends User
{
    protected $table = 'clients';

    public function getType(): string
    {
        return 'client';
    }

    public function missions()
    {
        return $this->hasMany(Mission::class);
    }
}

// ============================================================================
// app/Domain/Financial/Models/Sequestre.php
// ============================================================================

namespace App\Domain\Financial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Marketplace\Models\Mission;

class Sequestre extends Model
{
    protected $table = 'sequestres';

    protected $fillable = [
        'mission_id',
        'montant_total',
        'montant_materiel',
        'montant_main_oeuvre',
        'statut',
        'transaction_mobile_money_id',
        'date_blocage',
        'date_liberation',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'montant_materiel' => 'decimal:2',
        'montant_main_oeuvre' => 'decimal:2',
        'date_blocage' => 'datetime',
        'date_liberation' => 'datetime',
    ];

    const STATUT_BLOQUE = 'bloque';
    const STATUT_PARTIEL = 'partiel';
    const STATUT_LIBERE = 'libere';
    const STATUT_REMBOURSE = 'rembourse';

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function fragmenter(float $ratioMateriel = 0.65): void
    {
        $this->montant_materiel = $this->montant_total * $ratioMateriel;
        $this->montant_main_oeuvre = $this->montant_total * (1 - $ratioMateriel);
        $this->save();
    }

    public function libererMainOeuvre(float $montant): void
    {
        if ($montant > $this->montant_main_oeuvre) {
            throw new \Exception("Montant supérieur au disponible");
        }

        $this->montant_main_oeuvre -= $montant;
        
        if ($this->montant_main_oeuvre <= 0 && $this->montant_materiel <= 0) {
            $this->statut = self::STATUT_LIBERE;
            $this->date_liberation = now();
        } else {
            $this->statut = self::STATUT_PARTIEL;
        }
        
        $this->save();
    }
}

// ============================================================================
// app/Domain/Financial/Models/JetonMateriel.php
// ============================================================================

namespace App\Domain\Financial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JetonMateriel extends Model
{
    protected $table = 'jetons_materiel';

    protected $fillable = [
        'code',
        'sequestre_id',
        'artisan_id',
        'montant_initial',
        'montant_utilise',
        'montant_restant',
        'statut',
        'date_expiration',
        'fournisseur_id',
        'latitude_validation',
        'longitude_validation',
        'date_validation',
    ];

    protected $casts = [
        'montant_initial' => 'decimal:2',
        'montant_utilise' => 'decimal:2',
        'montant_restant' => 'decimal:2',
        'date_expiration' => 'datetime',
        'date_validation' => 'datetime',
        'latitude_validation' => 'float',
        'longitude_validation' => 'float',
    ];

    const STATUT_ACTIF = 'actif';
    const STATUT_UTILISE = 'utilise';
    const STATUT_EXPIRE = 'expire';
    const STATUT_ANNULE = 'annule';

    public static function genererCode(): string
    {
        return 'PA-' . strtoupper(Str::random(4));
    }

    public function valider(
        int $fournisseurId,
        float $montant,
        float $latitudeFournisseur,
        float $longitudeFournisseur
    ): bool {
        // Vérifier la proximité (< 100m)
        $distance = $this->calculerDistance(
            $latitudeFournisseur,
            $longitudeFournisseur
        );

        if ($distance > 0.1) { // 100m = 0.1km
            throw new \Exception("Distance trop grande entre artisan et fournisseur");
        }

        if ($montant > $this->montant_restant) {
            throw new \Exception("Montant supérieur au solde disponible");
        }

        $this->montant_utilise += $montant;
        $this->montant_restant -= $montant;
        $this->fournisseur_id = $fournisseurId;
        $this->latitude_validation = $latitudeFournisseur;
        $this->longitude_validation = $longitudeFournisseur;
        $this->date_validation = now();

        if ($this->montant_restant <= 0) {
            $this->statut = self::STATUT_UTILISE;
        }

        return $this->save();
    }

    private function calculerDistance(float $lat2, float $lon2): float
    {
        // Formule Haversine
        $earthRadius = 6371; // km

        $lat1 = $this->artisan->latitude;
        $lon1 = $this->artisan->longitude;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function artisan()
    {
        return $this->belongsTo(\App\Domain\Identity\Models\Artisan\Artisan::class);
    }

    public function fournisseur()
    {
        return $this->belongsTo(\App\Domain\Identity\Models\Fournisseur\Fournisseur::class);
    }

    public function sequestre()
    {
        return $this->belongsTo(Sequestre::class);
    }
}

// ============================================================================
// app/Domain/Reputation/Models/ScoreNZassa.php
// ============================================================================

namespace App\Domain\Reputation\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Identity\Models\Artisan\Artisan;

class ScoreNZassa extends Model
{
    protected $table = 'scores_nzassa';

    protected $fillable = [
        'artisan_id',
        'score_total',
        'score_fiabilite',
        'score_integrite',
        'score_qualite',
        'score_reactivite',
        'nombre_chantiers',
        'nombre_chantiers_termines',
        'moyenne_notes_client',
        'temps_reponse_moyen_heures',
        'tentatives_contournement',
        'historique',
    ];

    protected $casts = [
        'score_total' => 'integer',
        'score_fiabilite' => 'float',
        'score_integrite' => 'float',
        'score_qualite' => 'float',
        'score_reactivite' => 'float',
        'historique' => 'array',
    ];

    const SEUIL_CREDIT = 700;

    public function artisan()
    {
        return $this->belongsTo(Artisan::class);
    }

    public function calculer(): void
    {
        // Fiabilité (40%)
        $fiabilite = $this->nombre_chantiers > 0
            ? ($this->nombre_chantiers_termines / $this->nombre_chantiers) * 40
            : 0;

        // Intégrité (30%)
        $integrite = max(0, 30 - ($this->tentatives_contournement * 10));

        // Qualité (20%)
        $qualite = ($this->moyenne_notes_client / 5) * 20;

        // Réactivité (10%)
        $reactivite = $this->temps_reponse_moyen_heures <= 2
            ? 10
            : max(0, 10 - ($this->temps_reponse_moyen_heures - 2));

        $this->score_fiabilite = $fiabilite;
        $this->score_integrite = $integrite;
        $this->score_qualite = $qualite;
        $this->score_reactivite = $reactivite;

        $ancienScore = $this->score_total;
        $this->score_total = round($fiabilite + $integrite + $qualite + $reactivite);

        // Historiser
        $this->historique[] = [
            'date' => now()->toISOString(),
            'ancien_score' => $ancienScore,
            'nouveau_score' => $this->score_total,
            'raison' => 'Recalcul automatique',
        ];

        $this->save();
    }

    public function estEligibleCredit(): bool
    {
        return $this->score_total >= self::SEUIL_CREDIT;
    }
}

// ============================================================================
// app/Http/Controllers/Api/V1/Auth/AuthController.php
// ============================================================================

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Domain\Identity\Models\Client\Client;
use App\Domain\Identity\Models\Artisan\Artisan;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenoms' => 'required|string|max:255',
            'telephone' => 'required|unique:clients,telephone|unique:artisans,telephone',
            'email' => 'nullable|email|unique:clients,email|unique:artisans,email',
            'password' => 'required|min:8|confirmed',
            'type' => 'required|in:client,artisan',
        ]);

        $userClass = $validated['type'] === 'client' ? Client::class : Artisan::class;

        $user = $userClass::create([
            'nom' => $validated['nom'],
            'prenoms' => $validated['prenoms'],
            'telephone' => $validated['telephone'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'statut' => 'actif',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'telephone' => 'required',
            'password' => 'required',
            'type' => 'required|in:client,artisan,fournisseur',
        ]);

        $userClass = match($validated['type']) {
            'client' => Client::class,
            'artisan' => Artisan::class,
            default => null,
        };

        $user = $userClass::where('telephone', $validated['telephone'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
}