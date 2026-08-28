<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    protected $table = 'contact_messages';

    protected $fillable = [
        'nom',
        'email',
        'telephone',
        'sujet',
        'message',
        'artisan_id',
        'statut',
        'priorite',
        'notes_admin',
        'reponse_envoyee',
        'traite_par_user_id',
        'traite_at',
        'ip_address',
    ];

    protected $casts = [
        'traite_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Artisan ciblé par le message si applicable.
     */
    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    /**
     * Administrateur ayant traité le message.
     */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par_user_id');
    }

    /**
     * Scopes
     */
    public function scopeNouveau($query)
    {
        return $query->where('statut', 'nouveau');
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeTraite($query)
    {
        return $query->where('statut', 'traite');
    }
}
