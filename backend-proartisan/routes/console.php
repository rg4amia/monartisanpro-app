<?php

use App\Console\Commands\AutoReleaseJalonsCommand;
use App\Console\Commands\DecayScoreCommand;
use App\Console\Commands\DriverWatchdogCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Force-Pass 72h : libération automatique des jalons sans réponse client
// Backlog Epic 9 — "Trigger B (Le Force-Pass)"
Schedule::command(AutoReleaseJalonsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/auto-release-jalons.log'));

// Dégradation temporelle du Score ProsArtisan (« La Rouille »)
// Backlog Epic 12 — Inactivité ≥ 60 jours → −5 pts / semaine
Schedule::command(DecayScoreCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/decay-score.log'));

// Watchdog Livreur : réaffectation automatique des courses inactives
// PRD §5 — « Réaffectation Automatique de Livreur (Driver Fallback) »
Schedule::command(DriverWatchdogCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/driver-watchdog.log'));
