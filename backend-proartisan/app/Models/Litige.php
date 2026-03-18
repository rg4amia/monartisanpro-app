<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Litige extends Model
{
    protected $fillable = [
        'mission_id', 'declencheur_id', 'type', 'description',
        'statut', 'decision', 'resolu_at', 'admin_notes'
    ];

    protected function casts(): array
    {
        return [
            'resolu_at' => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function declencheur()
    {
        return $this->belongsTo(User::class, 'declencheur_id');
    }
}
