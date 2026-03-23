<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'artisan_id', 'description', 'photos_json',
        'requested_sector_id', 'requested_trade_id',
        'gemini_category', 'gemini_urgency', 'gemini_estimation_min', 'gemini_estimation_max',
        'status', 'montant_total', 'montant_materiaux', 'montant_mo',
        'ratio_materiaux', 'referent_required', 'funds_frozen',
        'referent_validated_at', 'referent_validated_by',
        'client_latitude', 'client_longitude', 'client_address',
    ];

    protected function casts(): array
    {
        return [
            'photos_json'         => 'array',
            'montant_total'       => 'integer',
            'montant_materiaux'   => 'integer',
            'montant_mo'          => 'integer',
            'ratio_materiaux'     => 'decimal:4',
            'referent_required'   => 'boolean',
            'funds_frozen'        => 'boolean',
            'gemini_estimation_min' => 'integer',
            'gemini_estimation_max' => 'integer',
            'referent_validated_at' => 'datetime',
            'client_latitude'       => 'float',
            'client_longitude'      => 'float',
        ];
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function referent()
    {
        return $this->belongsTo(User::class, 'referent_validated_by');
    }

    public function requestedSector()
    {
        return $this->belongsTo(Sector::class, 'requested_sector_id');
    }

    public function requestedTrade()
    {
        return $this->belongsTo(Trade::class, 'requested_trade_id');
    }

    public function devis()
    {
        return $this->hasMany(Devis::class);
    }

    public function jalons()
    {
        return $this->hasMany(Jalon::class)->orderBy('ordre');
    }

    public function jcodes()
    {
        return $this->hasMany(JCode::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function litiges()
    {
        return $this->hasMany(Litige::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function devisAccepte()
    {
        return $this->hasOne(Devis::class)->where('statut', 'accepte');
    }
}
