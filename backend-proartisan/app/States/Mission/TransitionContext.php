<?php

namespace App\States\Mission;

use App\Models\User;

/**
 * Contexte de la transition en cours, posé par `MissionLifecycleService` :
 * l'acteur, le motif et les précisions à consigner dans l'historique, ainsi
 * que les indications lues par les gardes.
 *
 * Il ne vit que le temps d'une transition : hors de là, il est vide.
 */
final class TransitionContext
{
    private static bool $active = false;

    private static ?User $actor = null;

    private static ?string $reason = null;

    /** @var array<string, mixed> */
    private static array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function open(?User $actor, ?string $reason, array $data): void
    {
        self::$active = true;
        self::$actor = $actor;
        self::$reason = $reason;
        self::$data = $data;
    }

    public static function close(): void
    {
        self::$active = false;
        self::$actor = null;
        self::$reason = null;
        self::$data = [];
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function actor(): ?User
    {
        return self::$actor;
    }

    public static function reason(): ?string
    {
        return self::$reason;
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return self::$data;
    }

    public static function flag(string $key): bool
    {
        return (bool) (self::$data[$key] ?? false);
    }
}
