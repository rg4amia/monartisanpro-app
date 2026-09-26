<?php

namespace Tests\Support;

use App\Models\ScoreLedgerEntry;
use App\Models\User;

/**
 * Adosse le score stocké d'un utilisateur à son ledger : les décisions qui
 * jugent le score recalculé (jurés, micro-crédit) ignorent une colonne
 * `score_prosartisan` renseignée sans historique (Règle d'or 15).
 */
class Scores
{
    public static function backWithLedger(User ...$users): void
    {
        foreach ($users as $user) {
            ScoreLedgerEntry::create([
                'user_id' => $user->id,
                'event_type' => 'test_seed',
                'points' => (int) $user->score_prosartisan,
                'credibility_factor' => 1,
                'description' => 'Historique de score de test',
            ]);
        }
    }
}
