<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone', 'name', 'password', 'role', 'kyc_status',
        'score_nzassa', 'wallet_materiaux', 'wallet_mo', 'fcm_token',
        'commune_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'wallet_materiaux' => 'integer',
            'wallet_mo'        => 'integer',
            'score_nzassa'     => 'integer',
        ];
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

    // ── Méthodes GPS (SQL brut, compatible MySQL 5.7) ────────────────────────

    public function setPosition(float $lat, float $lng): void
    {
        DB::statement(
            'UPDATE users SET position = POINT(?, ?) WHERE id = ?',
            [$lng, $lat, $this->id]
        );
    }

    public function getPositionCoords(): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_X(position) as lng, ST_Y(position) as lat FROM users WHERE id = ? AND position IS NOT NULL',
            [$this->id]
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }
}
