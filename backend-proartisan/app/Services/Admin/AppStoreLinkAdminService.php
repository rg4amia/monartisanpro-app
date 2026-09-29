<?php

namespace App\Services\Admin;

use App\Models\AppStoreLink;
use App\Models\User;
use App\Services\AppStoreLinkService;

/**
 * Onglet « Applications mobiles » du backoffice (Chantier 16) : liens Google
 * Play et App Store affichés sur le site vitrine. Toute action est auditée
 * (Règle d'or 17).
 */
class AppStoreLinkAdminService
{
    public function __construct(
        private AppStoreLinkService $links,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panelData(): array
    {
        $links = AppStoreLink::query()
            ->with(['creator:id,name', 'publisher:id,name', 'disabler:id,name'])
            ->orderByRaw("CASE status WHEN 'publie' THEN 0 WHEN 'brouillon' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get();

        return [
            'appStoreLinks' => $links->map(fn (AppStoreLink $link) => $this->links->present($link))->values()->all(),
            'appStoreLinkOptions' => [
                'platforms' => AppStoreLink::PLATFORM_LABELS,
                'statuses' => AppStoreLink::STATUS_LABELS,
            ],
        ];
    }

    public function create(string $platform, string $url, User $admin): AppStoreLink
    {
        $link = $this->links->create($platform, $url, $admin);

        $this->audit->log('app_store_link.created', $link, [
            'magasin' => $link->platformLabel(),
            'url' => $link->url,
        ], $link->platformLabel(), $admin);

        return $link;
    }

    public function update(AppStoreLink $link, string $url, User $admin): void
    {
        $before = $link->url;
        $this->links->update($link, $url);

        if ($before !== $link->url) {
            $this->audit->log('app_store_link.updated', $link, [
                'magasin' => $link->platformLabel(),
                'avant' => $before,
                'apres' => $link->url,
            ], $link->platformLabel(), $admin);
        }
    }

    public function publish(AppStoreLink $link, User $admin): void
    {
        if ($link->isPublished()) {
            return;
        }

        $replaced = $this->links->publish($link, $admin);

        $this->audit->log('app_store_link.published', $link, [
            'magasin' => $link->platformLabel(),
            'url' => $link->url,
            'remplace' => $replaced?->url,
        ], $link->platformLabel(), $admin);
    }

    public function disable(AppStoreLink $link, User $admin): void
    {
        if ($link->status === AppStoreLink::STATUS_DISABLED) {
            return;
        }

        $before = $link->status;
        $this->links->disable($link, $admin);

        $this->audit->log('app_store_link.disabled', $link, [
            'magasin' => $link->platformLabel(),
            'url' => $link->url,
            'statut_avant' => $before,
        ], $link->platformLabel(), $admin);
    }

    public function delete(AppStoreLink $link, User $admin): void
    {
        $snapshot = ['magasin' => $link->platformLabel(), 'url' => $link->url, 'statut' => $link->status];
        $label = $link->platformLabel();

        $this->links->delete($link);

        $this->audit->log('app_store_link.deleted', null, $snapshot, $label, $admin);
    }
}
