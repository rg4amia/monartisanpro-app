<?php

namespace App\Models;

use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPermissions;

    protected $fillable = [
        'email',
        'phone',
        'name',
        'password',
        'role',
        'kyc_status',
        'account_status',
        'account_status_reason',
        'blocked_at',
        'score_prosartisan',
        'wallet_materiaux',
        'wallet_mo',
        'fcm_token',
        'commune_id',
        'device_fingerprint',
        'score_frozen',
        'google_2fa_secret',
        'cnmci_number',
        'cnmci_card_url',
        'cnmci_status',
    ];

    protected $hidden = ['password', 'remember_token', 'position', 'google_2fa_secret'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'blocked_at'       => 'datetime',
            'wallet_materiaux' => 'integer',
            'wallet_mo'        => 'integer',
            'score_prosartisan'=> 'integer',
            'score_frozen'     => 'boolean',
        ];
    }

    protected $appends = ['coordinates'];

    protected static function booted()
    {
        if (app()->runningUnitTests()) {
            return;
        }

        static::saved(function (User $user) {
            if ($user->role === 'fournisseur' && ! $user->fournisseurAgree()->exists()) {
                $lat = 5.3300;
                $lng = -4.0620;

                if (config('database.default') === 'sqlite') {
                    DB::table('fournisseurs_agrees')->insert([
                        'user_id'      => $user->id,
                        'nom_boutique' => 'Quincaillerie de ' . ($user->name ?? $user->phone),
                        'statut'       => 'en_attente',
                        'position'     => "$lat,$lng",
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                } else {
                    DB::statement(
                        "INSERT INTO fournisseurs_agrees (user_id, nom_boutique, statut, position, created_at, updated_at) 
                         VALUES (?, ?, ?, POINT(?, ?), ?, ?)",
                        [
                            $user->id,
                            'Quincaillerie de ' . ($user->name ?? $user->phone),
                            'en_attente',
                            $lng,
                            $lat,
                            now(),
                            now()
                        ]
                    );
                }
            }
        });
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeArtisans($q)
    {
        return $q->where('role', 'artisan');
    }

    public function scopeKycActif($q)
    {
        return $q->where('kyc_status', 'actif');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function artisanProfile()
    {
        return $this->hasOne(ArtisanProfile::class);
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function fournisseurAgree()
    {
        return $this->hasOne(FournisseurAgree::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class, 'supplier_id');
    }

    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class);
    }

    public function missionsClient()
    {
        return $this->hasMany(Mission::class, 'client_id');
    }

    public function missionsArtisan()
    {
        return $this->hasMany(Mission::class, 'artisan_id');
    }

    public function userNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function evaluationsRecues()
    {
        return $this->hasMany(Evaluation::class, 'evalue_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getCoordinatesAttribute(): ?array
    {
        return $this->getPositionCoords();
    }

    public function isKycActif(): bool
    {
        return $this->kyc_status === 'actif';
    }

    public function isArtisan(): bool
    {
        return $this->role === 'artisan';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isFournisseur(): bool
    {
        return $this->role === 'fournisseur';
    }

    public function isLivreur(): bool
    {
        return $this->role === 'livreur';
    }

    public function isAccountActive(): bool
    {
        return ($this->account_status ?? 'actif') === 'actif';
    }

    // ── Méthodes GPS (SQL brut, compatible MySQL 5.7) ────────────────────────

    public function setPosition(float $lat, float $lng): void
    {
        if (config('database.default') === 'sqlite') {
            $this->forceFill([
                'position' => $lat . ',' . $lng,
            ])->save();

            return;
        }

        DB::statement(
            "UPDATE users SET position = POINT(?, ?) WHERE id = ?",
            [$lng, $lat, $this->id]
        );
    }

    public function getPositionCoords(): ?array
    {
        if (config('database.default') === 'sqlite') {
            $row = DB::table('users')
                ->select('position')
                ->where('id', $this->id)
                ->first();

            if (! $row || ! is_string($row->position) || trim($row->position) === '') {
                return null;
            }

            $parts = explode(',', $row->position);

            if (count($parts) !== 2) {
                return null;
            }

            return [
                'lat' => (float) $parts[0],
                'lng' => (float) $parts[1],
            ];
        }

        $row = DB::selectOne(
            'SELECT ST_X(position) as lng, ST_Y(position) as lat FROM users WHERE id = ? AND position IS NOT NULL',
            [$this->id]
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }

    public function isCnmciVerified(): bool
    {
        return $this->cnmci_status === 'valide';
    }

    public function scopeCnmciVerified($query)
    {
        return $query->where('cnmci_status', 'valide');
    }
}
