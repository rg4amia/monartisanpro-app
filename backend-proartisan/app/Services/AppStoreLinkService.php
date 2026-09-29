<?php

namespace App\Services;

use App\Models\AppStoreLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Liens de téléchargement de l'application mobile (Chantier 16).
 *
 * Cycle : brouillon → publié → désactivé. Un lien publié ne se modifie pas
 * (le site n'affiche jamais une adresse non relue) et un seul lien est publié
 * par magasin : en valider un nouveau désactive le précédent.
 */
class AppStoreLinkService
{
    /**
     * Liens publiés, un par magasin, pour le site vitrine.
     *
     * @return list<array{platform: string, label: string, url: string}>
     */
    public function publicLinks(): array
    {
        return AppStoreLink::query()
            ->where('status', AppStoreLink::STATUS_PUBLISHED)
            ->whereIn('platform', array_keys(AppStoreLink::PLATFORM_LABELS))
            ->orderByDesc('published_at')
            ->get()
            ->unique('platform')
            ->sortBy(fn (AppStoreLink $link) => array_search($link->platform, array_keys(AppStoreLink::PLATFORM_LABELS), true))
            ->map(fn (AppStoreLink $link) => [
                'platform' => $link->platform,
                'label' => $link->platformLabel(),
                'url' => $link->url,
            ])
            ->values()
            ->all();
    }

    public function create(string $platform, string $url, User $admin): AppStoreLink
    {
        $this->assertValidUrl($platform, $url);

        return AppStoreLink::create([
            'platform' => $platform,
            'url' => trim($url),
            'status' => AppStoreLink::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function update(AppStoreLink $link, string $url): AppStoreLink
    {
        if ($link->isPublished()) {
            throw ValidationException::withMessages([
                'url' => 'Un lien publié ne se modifie pas : désactivez-le, ou créez un nouveau lien puis validez-le pour le remplacer.',
            ]);
        }

        $this->assertValidUrl($link->platform, $url);
        $link->update(['url' => trim($url)]);

        return $link;
    }

    /**
     * Valide le lien : il est publié sur le site et remplace le lien publié
     * du même magasin, qui est désactivé.
     *
     * @return AppStoreLink|null Le lien remplacé, s'il y en avait un.
     */
    public function publish(AppStoreLink $link, User $admin): ?AppStoreLink
    {
        if ($link->isPublished()) {
            return null;
        }

        // L'adresse est revérifiée : un lien créé avant un durcissement
        // des règles ne doit pas être publié sans contrôle.
        $this->assertValidUrl($link->platform, $link->url);

        return DB::transaction(function () use ($link, $admin) {
            $previous = AppStoreLink::query()
                ->where('platform', $link->platform)
                ->where('status', AppStoreLink::STATUS_PUBLISHED)
                ->whereKeyNot($link->id)
                ->lockForUpdate()
                ->get();

            foreach ($previous as $old) {
                $this->disable($old, $admin);
            }

            $replaced = $previous->first();

            $link->update([
                'status' => AppStoreLink::STATUS_PUBLISHED,
                'published_by' => $admin->id,
                'published_at' => now(),
                'disabled_by' => null,
                'disabled_at' => null,
            ]);

            return $replaced;
        });
    }

    public function disable(AppStoreLink $link, User $admin): void
    {
        if ($link->status === AppStoreLink::STATUS_DISABLED) {
            return;
        }

        $link->update([
            'status' => AppStoreLink::STATUS_DISABLED,
            'disabled_by' => $admin->id,
            'disabled_at' => now(),
        ]);
    }

    public function delete(AppStoreLink $link): void
    {
        if ($link->isPublished()) {
            throw ValidationException::withMessages([
                'link' => 'Désactivez ce lien avant de le supprimer : il est affiché sur le site.',
            ]);
        }

        $link->delete();
    }

    /**
     * Refuse toute adresse qui ne mène pas à la fiche de l'application sur
     * le magasin annoncé.
     */
    public function assertValidUrl(string $platform, string $url): void
    {
        if (! array_key_exists($platform, AppStoreLink::PLATFORM_LABELS)) {
            throw ValidationException::withMessages(['platform' => 'Choisissez Google Play ou App Store.']);
        }

        if (! $this->isValidUrl($platform, trim($url))) {
            throw ValidationException::withMessages([
                'url' => $platform === AppStoreLink::PLATFORM_ANDROID
                    ? 'Adresse Google Play invalide. Format attendu : https://play.google.com/store/apps/details?id=com.exemple.app'
                    : 'Adresse App Store invalide. Format attendu : https://apps.apple.com/ci/app/nom-de-l-app/id123456789',
            ]);
        }
    }

    public function isValidUrl(string $platform, string $url): bool
    {
        if (strlen($url) > 500) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if ($platform === AppStoreLink::PLATFORM_ANDROID) {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['id'] ?? null;

            return $host === 'play.google.com'
                && rtrim($path, '/') === '/store/apps/details'
                && is_string($id)
                && preg_match('/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)+$/', $id) === 1;
        }

        return $host === 'apps.apple.com'
            && preg_match('#^/([a-z]{2}/)?app/([^/]+/)?id\d+/?$#i', $path) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(AppStoreLink $link): array
    {
        return [
            'id' => $link->id,
            'platform' => $link->platform,
            'platform_label' => $link->platformLabel(),
            'url' => $link->url,
            'status' => $link->status,
            'status_label' => AppStoreLink::STATUS_LABELS[$link->status] ?? $link->status,
            'created_by' => $link->creator?->name,
            'created_at' => $link->created_at?->toIso8601String(),
            'published_by' => $link->publisher?->name,
            'published_at' => $link->published_at?->toIso8601String(),
            'disabled_by' => $link->disabler?->name,
            'disabled_at' => $link->disabled_at?->toIso8601String(),
        ];
    }
}
