<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'kyc_status',
        'phone_verified_at',
        'status',
        'suspended_at',
        'suspended_reason',
        'banned_at',
        'ban_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the KYC documents for the user.
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /**
     * Get the artisan profile for the user.
     */
    public function artisanProfile(): HasOne
    {
        return $this->hasOne(ArtisanProfile::class);
    }

    /**
     * Get the artisan score for the user.
     */
    public function artisanScore(): HasOne
    {
        return $this->hasOne(ArtisanScore::class, 'artisan_id');
    }

    /**
     * Get all reviews for the artisan.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'artisan_id');
    }

    /**
     * Get all projects where user is the artisan.
     */
    public function artisanProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'artisan_id');
    }

    /**
     * Get all projects where user is the client.
     */
    public function clientProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    /**
     * Check if user is an artisan.
     */
    public function isArtisan(): bool
    {
        return $this->role === 'artisan';
    }

    /**
     * Check if user is a client.
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user's KYC is approved.
     */
    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }
}
