<?php

use App\Console\Commands\AdminHealthCheckCommand;
use App\Console\Commands\AutoApproveMissionCompletionCommand;
use App\Console\Commands\AutoReleaseJalonsCommand;
use App\Console\Commands\DecayScoreCommand;
use App\Console\Commands\DriverWatchdogCommand;
use App\Console\Commands\ExpireArtisanRequestsCommand;
use App\Console\Commands\ExpireJuryReviewsCommand;
use App\Console\Commands\ExpireRecruitmentOffersCommand;
use App\Console\Commands\ExpireUnpaidOrdersCommand;
use App\Console\Commands\PurgeReadNotificationsCommand;
use App\Console\Commands\RemindUnpaidDeliveryFaresCommand;
use App\Console\Commands\RetryFailedPayoutsCommand;
use App\Console\Commands\SendNotificationCampaignsCommand;
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

// Santé du backoffice + alerte Telegram (Chantier C7 — P2-12)
Schedule::command(AdminHealthCheckCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/admin-health-check.log'));

// Chantier 10 — relance des virements Mobile Money échoués (artisans, livreurs)
Schedule::command(RetryFailedPayoutsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/retry-failed-payouts.log'));

// Chantier 11 — annulation des commandes de matériaux impayées (stock restitué)
Schedule::command(ExpireUnpaidOrdersCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/expire-unpaid-orders.log'));

// Chantier 11 — relance des courses livrées impayées, restriction au-delà du plafond
Schedule::command(RemindUnpaidDeliveryFaresCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/remind-unpaid-delivery-fares.log'));

// Chantier 12 — remplacement des jurés n'ayant pas voté sous 48 h
Schedule::command(ExpireJuryReviewsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/jury-expire-overdue.log'));

// Chantier 19 — demandes de devis sans réponse de l'artisan (relance à mi-délai, retrait à l'échéance)
Schedule::command(ExpireArtisanRequestsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/missions-expire-artisan-requests.log'));

// Chantier 19 — clôture automatique des missions sans validation finale du client
Schedule::command(AutoApproveMissionCompletionCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/missions-auto-approve-completion.log'));

// Module Recrutement — clôture automatique des offres expirées
Schedule::command(ExpireRecruitmentOffersCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/recruitment-expire.log'));

// Chantier 14, lot D — envoi par lots des campagnes push et SMS du backoffice
Schedule::command(SendNotificationCampaignsCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/notification-campaigns.log'));

// Chantier 14, lot E — purge des notifications lues depuis plus de 12 mois
Schedule::command(PurgeReadNotificationsCommand::class)
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/notifications-purge.log'));
