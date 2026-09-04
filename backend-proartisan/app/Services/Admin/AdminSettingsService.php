<?php

namespace App\Services\Admin;

use App\Models\Setting;
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
}
