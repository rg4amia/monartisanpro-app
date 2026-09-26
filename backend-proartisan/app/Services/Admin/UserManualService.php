<?php

namespace App\Services\Admin;

/**
 * Manuel d'utilisation de ProsArtisan, consulté et téléchargé depuis le
 * backoffice. Source unique : `docs/produit/manuel-utilisation.html`
 * (`config('prosartisan.user_manual_path')`), mise à jour dans le même
 * commit que toute évolution visible des utilisateurs.
 */
class UserManualService
{
    public function path(): string
    {
        return (string) config('prosartisan.user_manual_path');
    }

    public function available(): bool
    {
        $path = $this->path();

        return $path !== '' && is_file($path) && is_readable($path);
    }

    public function contents(): string
    {
        $contents = $this->available() ? file_get_contents($this->path()) : false;
        if ($contents === false) {
            throw new \RuntimeException("Le manuel d'utilisation est introuvable sur le serveur.");
        }

        return $contents;
    }

    /** Nom du fichier téléchargé, daté de la dernière mise à jour du manuel. */
    public function downloadName(): string
    {
        return 'manuel-prosartisan-'.date('Y-m-d', (int) filemtime($this->path())).'.html';
    }

    /**
     * Props de l'onglet « Manuel d'utilisation ». Sans fichier, l'onglet le
     * dit plutôt que d'afficher une page vide (Règle d'or 29).
     *
     * @return array{available: bool, html: ?string, updatedAt: ?string, sizeKb: ?int}
     */
    public function summary(): array
    {
        if (! $this->available()) {
            return ['available' => false, 'html' => null, 'updatedAt' => null, 'sizeKb' => null];
        }

        return [
            'available' => true,
            'html' => $this->contents(),
            'updatedAt' => date(DATE_ATOM, (int) filemtime($this->path())),
            'sizeKb' => (int) ceil(filesize($this->path()) / 1024),
        ];
    }
}
