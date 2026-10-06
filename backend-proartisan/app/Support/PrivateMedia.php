<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Fichiers privés des utilisateurs : photos de course, reçus de bons
 * matériels, photos d'étape et de litige, médias de discussion, photos de
 * mission, messages vocaux.
 *
 * Décision du 06/10/2026 : seules les images de catalogue et les réalisations
 * d'un artisan sont publiques. Tout le reste vit sur le disque privé et se lit
 * par un lien signé à durée limitée, jamais par une adresse permanente
 * (Règle d'or 40).
 *
 * En base, une colonne porte l'adresse *canonique* du fichier, sans signature.
 * Elle ne s'ouvre pas telle quelle (403). Les conversions `PrivateMediaUrl` et
 * `PrivateMediaUrlList` la signent à chaque lecture, et n'acceptent en écriture
 * qu'un lien dont la signature est encore valide : un lien périmé ou recopié
 * ne se « rafraîchit » pas en le renvoyant au serveur.
 */
final class PrivateMedia
{
    public const DISK = 'local';

    /** Dossier du disque privé qui porte ces fichiers. */
    public const ROOT = 'media';

    public const ROUTE = 'media.private.file';

    /** Préfixe de l'adresse canonique, sous lequel la route est servie. */
    public const URL_PREFIX = '/media/prive/';

    /**
     * Types acceptés, jugés sur le contenu, et extension d'enregistrement.
     * L'extension ne vient jamais du nom transmis (Règle d'or 117).
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-matroska' => 'mkv',
        'video/3gpp' => '3gp',
        'video/x-m4v' => 'm4v',
        'video/webm' => 'webm',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-aac' => 'aac',
        'audio/x-hx-aac-adts' => 'aac',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
    ];

    /**
     * Enregistre un fichier sur le disque privé, sous un nom aléatoire, et
     * renvoie son chemin relatif (`orders/pickup/<aléa>.jpg`).
     *
     * @param  string|null  $extension  extension déjà établie par l'appelant sur le contenu
     */
    public static function store(UploadedFile $file, string $directory, ?string $extension = null): string
    {
        $extension ??= self::EXTENSIONS[strtolower((string) $file->getMimeType())] ?? 'bin';
        $directory = trim($directory, '/');
        $filename = Str::random(40).'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(self::ROOT.'/'.$directory, $file, $filename);

        return $directory.'/'.$filename;
    }

    /** Enregistre un fichier et renvoie directement son lien signé. */
    public static function storeAndSign(UploadedFile $file, string $directory, ?string $extension = null): string
    {
        return self::signedUrl(self::store($file, $directory, $extension));
    }

    /** Adresse canonique, sans signature : c'est elle qui est enregistrée en base. */
    public static function canonicalUrl(string $path): string
    {
        return url(self::URL_PREFIX.ltrim($path, '/'));
    }

    /** Lien de lecture signé, valable `prosartisan.private_media.url_ttl_minutes`. */
    public static function signedUrl(string $path): string
    {
        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addMinutes((int) config('prosartisan.private_media.url_ttl_minutes', 120)),
            ['path' => ltrim($path, '/')],
        );
    }

    /**
     * Chemin relatif d'un fichier privé d'après son adresse (canonique ou
     * signée), ou null si l'adresse n'en désigne pas un.
     */
    public static function pathFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        if (! is_string($urlPath)) {
            return null;
        }

        $position = strpos($urlPath, self::URL_PREFIX);
        if ($position === false) {
            return null;
        }

        $path = rawurldecode(substr($urlPath, $position + strlen(self::URL_PREFIX)));

        return self::isSafePath($path) ? $path : null;
    }

    public static function isPrivateUrl(mixed $url): bool
    {
        return self::pathFromUrl($url) !== null;
    }

    /**
     * Valeur à enregistrer en base pour une adresse reçue.
     *
     * - adresse étrangère aux fichiers privés (ancienne adresse publique,
     *   image de catalogue) : rendue telle quelle ;
     * - lien signé encore valide : son adresse canonique ;
     * - adresse privée sans signature valide : acceptée seulement si elle est
     *   déjà celle enregistrée (`$stored`), refusée (null) sinon.
     */
    public static function toStored(mixed $url, mixed $stored = null): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = self::pathFromUrl($url);
        if ($path === null) {
            // Une adresse qui vise le préfixe privé avec un chemin refusé n'est jamais conservée.
            return str_contains($url, self::URL_PREFIX) ? null : $url;
        }

        if (self::hasValidSignature($url)) {
            return self::canonicalUrl($path);
        }

        return is_string($stored) && self::pathFromUrl($stored) === $path
            ? self::canonicalUrl($path)
            : null;
    }

    /** Adresse à remettre à l'appelant pour une valeur enregistrée. */
    public static function toReadable(mixed $stored): mixed
    {
        $path = self::pathFromUrl($stored);

        return $path === null ? $stored : self::signedUrl($path);
    }

    public static function hasValidSignature(string $url): bool
    {
        try {
            return URL::hasValidSignature(Request::create($url));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function exists(string $path): bool
    {
        return self::isSafePath($path) && Storage::disk(self::DISK)->exists(self::ROOT.'/'.$path);
    }

    public static function contents(string $path): ?string
    {
        return self::exists($path) ? Storage::disk(self::DISK)->get(self::ROOT.'/'.$path) : null;
    }

    public static function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path(self::ROOT.'/'.$path);
    }

    public static function mimeType(string $path): ?string
    {
        if (! self::exists($path)) {
            return null;
        }

        return mime_content_type(self::absolutePath($path)) ?: null;
    }

    public static function delete(string $path): bool
    {
        return self::exists($path) && Storage::disk(self::DISK)->delete(self::ROOT.'/'.$path);
    }

    /**
     * Un chemin relatif simple, sans remontée de dossier ni caractère inattendu.
     */
    public static function isSafePath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '..')
            && preg_match('#^[A-Za-z0-9_\-]+(?:/[A-Za-z0-9_\-.]+)+$#', $path) === 1;
    }
}
