<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = [
        'mission_id', 'evaluateur_id', 'evalue_id', 'note', 'commentaire',
        'fiabilite', 'integrite', 'qualite', 'reactivite',
    ];

    protected function casts(): array
    {
        return [
            'note'       => 'integer',
            'fiabilite'  => 'integer',
            'integrite'  => 'integer',
            'qualite'    => 'integer',
            'reactivite' => 'integer',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function evaluateur()
    {
        return $this->belongsTo(User::class, 'evaluateur_id');
    }

    public function evalue()
    {
        return $this->belongsTo(User::class, 'evalue_id');
    }
}
