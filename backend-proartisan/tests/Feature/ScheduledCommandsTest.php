<?php

use Illuminate\Console\Scheduling\Schedule;

/*
 * Une commande de maintenance qui existe sans être planifiée ne s'exécute
 * jamais en production : `jury:expire-overdue` est restée ainsi, et un juré
 * qui ne votait pas bloquait le litige indéfiniment.
 */
it('planifie les commandes de maintenance métier', function (string $signature) {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command);

    expect($commands->contains(fn (string $command) => str_contains($command, $signature)))
        ->toBeTrue("« {$signature} » n'est pas planifiée dans routes/console.php");
})->with([
    'jury:expire-overdue',
    'prosartisan:retry-failed-payouts',
    'prosartisan:expire-unpaid-orders',
    'prosartisan:remind-unpaid-delivery-fares',
    'prosartisan:driver-watchdog',
    'admin:health-check',
]);
