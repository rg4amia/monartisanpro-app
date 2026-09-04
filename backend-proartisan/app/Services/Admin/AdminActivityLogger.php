<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Écrit le journal d'audit des actions administrateur (Chantier C3 / P0-4).
 *
 * Injecté dans les services du backoffice qui manipulent le KYC, la finance,
 * les litiges et les comptes. Le logging est best-effort : une erreur ici ne
 * doit jamais faire échouer l'action métier (d'où le try/catch global).
 */
class AdminActivityLogger
{
    /**
     * @param  array<string, mixed>  $context  Détail de l'action (avant/après, décision, motif…).
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?string $subjectLabel = null,
        ?User $actor = null,
    ): void {
        try {
            $actor ??= Auth::user();
            $request = request();

            AdminActivityLog::create([
                'admin_id' => $actor?->getKey(),
                'admin_name' => $actor?->name,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => Str::limit($subjectLabel ?? $this->guessLabel($subject) ?? '', 200, ''),
                'context' => $context !== [] ? $context : null,
                'ip_address' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 255, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminActivityLogger: '.$e->getMessage());
        }
    }

    private function guessLabel(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['name', 'code', 'titre', 'title', 'nom_boutique', 'key'] as $attribute) {
            if (! empty($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
