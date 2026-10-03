<?php

/**
 * Garde de non-régression (Chantier 19) : aucun code de `app/` n'écrit l'état
 * d'une mission sans passer par `MissionLifecycleService`.
 *
 * Écrire la colonne `status` directement sautait les gardes de la machine à
 * états et n'inscrivait rien dans l'historique : financement, litige, clôture
 * et annulation en étaient absents. Ce test relit les sources, comme
 * `MariaDbSpatialSyntaxGuardTest`.
 */
$appPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';

/** Seuls endroits où l'état d'une mission peut être manipulé directement. */
$allowed = [
    'Services'.DIRECTORY_SEPARATOR.'MissionLifecycleService.php',
    // Création : l'état initial n'est pas une transition.
    'Services'.DIRECTORY_SEPARATOR.'MissionService.php',
    'States'.DIRECTORY_SEPARATOR.'Mission',
    // Commande de simulation locale, hors production.
    'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'SimulateFullLifecycleCommand.php',
];

$sources = function () use ($appPath, $allowed): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($appPath) + 1);
        foreach ($allowed as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                continue 2;
            }
        }

        $files[$relative] = file_get_contents($file->getPathname());
    }

    return $files;
};

it('ne déclenche aucune transition de mission hors du service de cycle de vie', function () use ($sources) {
    $offenders = [];

    foreach ($sources() as $path => $code) {
        // `$mission->status->transitionTo(…)`, `$jalon->mission->status->transitionTo(…)`…
        if (preg_match('/mission(\(\))?->status->transitionTo\(/i', $code)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Transition directe : passer par MissionLifecycleService::transition().');
});

it('n\'écrit jamais l\'état d\'une mission par une mise à jour directe', function () use ($sources) {
    $states = 'draft|pending_artisan_acceptance|pending_funding|funded_locked|in_progress|pending_approval|completed|disputed|cancelled';
    $classes = 'Draft|PendingArtisanAcceptance|PendingFunding|FundedLocked|InProgress|PendingApproval|Completed|Disputed|Cancelled';
    $offenders = [];

    foreach ($sources() as $path => $code) {
        // `$mission->update([... 'status' => 'completed' ...])` ou `'status' => CompletedState::class`,
        // sur une variable ou une relation nommée « mission ».
        $pattern = '/mission\w*(\(\))?\s*->\s*update\(\s*\[[^\]]*\'status\'\s*=>\s*(\'('.$states.')\'|('.$classes.')State::class)/is';

        if (preg_match($pattern, $code)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Écriture directe du statut : passer par MissionLifecycleService::transition().');
});
