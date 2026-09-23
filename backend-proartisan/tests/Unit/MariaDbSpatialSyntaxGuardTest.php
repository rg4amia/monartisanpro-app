<?php

/**
 * Garde de non-régression — syntaxe spatiale compatible MariaDB.
 *
 * La production tourne sur MariaDB 11.8 (Hostinger), qui refuse deux syntaxes
 * propres à MySQL 8 : la colonne `POINT SRID 4326` et le mutateur à deux
 * arguments `ST_SRID(POINT(...), 4326)`. Les tests tournent sur SQLite, qui
 * n'exécute aucune fonction spatiale : une telle requête passe la suite et le
 * poste local (MySQL 8.4) puis casse uniquement en production. Ce test relit
 * donc les sources (code applicatif ET tests, qui tournent aussi sur MariaDB en CI)
 * au lieu d'exécuter les requêtes.
 */
function spatialSourceFiles(): array
{
    $files = [];

    foreach (['app', 'database/migrations', 'routes', 'tests'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$dir));
        foreach ($iterator as $file) {
            // Le garde lui-même cite les syntaxes interdites dans sa documentation.
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== basename(__FILE__)) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('n\'utilise jamais le mutateur MySQL 8 ST_SRID(geometrie, srid)', function () {
    $offenders = [];

    foreach (spatialSourceFiles() as $path) {
        // ST_SRID( … , <srid> ) : une virgule au premier niveau de parenthèses.
        if (preg_match('/ST_SRID\s*\((?:[^()]|\([^()]*\))*,/i', file_get_contents($path))) {
            $offenders[] = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('ne déclare jamais de colonne POINT SRID (syntaxe MySQL 8)', function () {
    $offenders = [];

    foreach (spatialSourceFiles() as $path) {
        if (preg_match('/POINT\s+SRID\s+\d+/i', file_get_contents($path))) {
            $offenders[] = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($offenders)->toBeEmpty();
});
