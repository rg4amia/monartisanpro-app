<?php

namespace App\Services\Admin;

use App\Models\AiUserQuota;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminSettingsService
{
    public function __construct(private AdminActivityLogger $audit) {}

    public function updateSetting(Setting $setting, ?string $value): Setting
    {
        $before = $setting->value;

        $setting->update(['value' => $value]);

        $this->audit->log('setting.updated', $setting, [
            'key' => $setting->key ?? $setting->getKey(),
            'before' => $before,
            'after' => $value,
        ]);

        return $setting;
    }

    /**
     * Paramètres de la brique IA, stockés dans la table clé/valeur `ai_settings`.
     *
     * @param  array{daily_user_limit: int|string, ai_enabled: string}  $data
     */
    public function updateAiSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            DB::table('ai_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now()],
            );
        }

        $this->audit->log('ai_settings.updated', null, $data);
    }

    /**
     * Surcharge de quota IA pour un utilisateur mobile. Si aucune contrainte
     * n'est posée (pas de limite, pas de blocage, pas de note), la ligne
     * d'exception est supprimée : l'utilisateur repasse sur la limite globale.
     *
     * @param  array{daily_limit: int|null, monthly_limit: int|null, blocked: bool, note: string|null}  $data
     */
    public function updateUserAiQuota(User $user, array $data): ?AiUserQuota
    {
        $isDefault = ($data['daily_limit'] ?? null) === null
            && ($data['monthly_limit'] ?? null) === null
            && empty($data['blocked'])
            && trim((string) ($data['note'] ?? '')) === '';

        if ($isDefault) {
            AiUserQuota::query()->where('user_id', $user->id)->delete();
            $this->audit->log('ai_quota.reset', $user, [], subjectLabel: $user->name);

            return null;
        }

        $quota = AiUserQuota::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'daily_limit' => $data['daily_limit'] ?? null,
                'monthly_limit' => $data['monthly_limit'] ?? null,
                'blocked' => (bool) ($data['blocked'] ?? false),
                'note' => $data['note'] ?? null,
                'updated_by' => Auth::id(),
            ],
        );

        $this->audit->log('ai_quota.updated', $user, $data, subjectLabel: $user->name);

        return $quota;
    }
}
