<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préférences de notification d'un utilisateur. Au lot D, seul l'accord aux
 * messages promotionnels (push, désactivé par défaut) existe : une campagne
 * promotionnelle ne vise que les comptes qui l'ont donné.
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'promotional_push'];

    protected $attributes = ['promotional_push' => false];

    protected function casts(): array
    {
        return ['promotional_push' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
