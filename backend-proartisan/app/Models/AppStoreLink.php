<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien de téléchargement de l'application mobile (Chantier 16). Seul un lien
 * `publie` apparaît sur le site vitrine, un seul par magasin.
 */
class AppStoreLink extends Model
{
    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_IOS = 'ios';

    public const STATUS_DRAFT = 'brouillon';

    public const STATUS_PUBLISHED = 'publie';

    public const STATUS_DISABLED = 'desactive';

    public const PLATFORM_LABELS = [
        self::PLATFORM_ANDROID => 'Google Play',
        self::PLATFORM_IOS => 'App Store',
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_PUBLISHED => 'Publié',
        self::STATUS_DISABLED => 'Désactivé',
    ];

    protected $fillable = [
        'platform',
        'url',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'disabled_by',
        'disabled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function disabler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function platformLabel(): string
    {
        return self::PLATFORM_LABELS[$this->platform] ?? $this->platform;
    }
}
