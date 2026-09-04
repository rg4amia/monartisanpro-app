<?php

namespace App\Services\Admin;

use App\Models\Mission;
use App\Models\Notification;
use App\Models\ScoreLedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chantier C7 (P2-12) — santé opérationnelle du backoffice.
 *
 * Agrège quatre signaux critiques : files d'attente en échec, webhooks de
 * paiement KO, tentatives de fraude GPS J-Code (> 100 m) et missions bloquées
 * au seuil Référent (> 2 000 000 FCFA).
 */
class AdminObservabilityService
{
    /** Statuts de mission actifs concernés par la validation Référent. */
    private const REFERENT_BLOCKED_STATUSES = ['funded_locked', 'in_progress', 'disputed'];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'queue' => $this->queue(),
            'payments' => $this->payments(),
            'fraud' => $this->fraud(),
            'referent' => $this->referent(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Compteurs critiques uniquement (pour la décision d'alerte Telegram).
     *
     * @return array{failed_jobs: int, failed_payments_24h: int, gps_fraud_7d: int, referent_blocked: int}
     */
    public function criticalCounts(): array
    {
        return [
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            'failed_payments_24h' => Transaction::where('statut', 'echoue')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'gps_fraud_7d' => ScoreLedgerEntry::where('event_type', 'fraude_gps_tentative')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'referent_blocked' => Mission::where('referent_required', true)
                ->whereIn('status', self::REFERENT_BLOCKED_STATUSES)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $oldestPending = DB::table('jobs')->min('available_at');

        return [
            'pending' => (int) DB::table('jobs')->count(),
            'failed' => (int) DB::table('failed_jobs')->count(),
            'oldest_pending_minutes' => $oldestPending
                ? (int) now()->diffInMinutes(Carbon::createFromTimestamp($oldestPending))
                : 0,
            'recent' => DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->get(['id', 'uuid', 'queue', 'exception', 'failed_at'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'queue' => $row->queue,
                    'exception' => Str::of($row->exception)->before("\n")->limit(160)->value(),
                    'failed_at' => $row->failed_at,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payments(): array
    {
        $failed = Transaction::where('statut', 'echoue');

        return [
            'failed_24h' => (clone $failed)->where('created_at', '>=', now()->subDay())->count(),
            'failed_total' => (clone $failed)->count(),
            'recent' => (clone $failed)
                ->latest()
                ->limit(15)
                ->get(['id', 'provider', 'type', 'montant', 'reference_externe', 'error_message', 'created_at'])
                ->map(fn (Transaction $tx) => [
                    'id' => $tx->id,
                    'provider' => $tx->provider instanceof \BackedEnum ? $tx->provider->value : $tx->provider,
                    'type' => $tx->type,
                    'montant' => (int) $tx->montant,
                    'reference' => $tx->reference_externe,
                    'error' => $tx->error_message,
                    'created_at' => optional($tx->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fraud(): array
    {
        $attempts = ScoreLedgerEntry::where('event_type', 'fraude_gps_tentative');

        return [
            'gps_attempts_7d' => (clone $attempts)->where('created_at', '>=', now()->subDays(7))->count(),
            'gps_attempts_total' => (clone $attempts)->count(),
            'unread_alerts' => Notification::whereNull('read_at')->where('type', 'alert')->count(),
            'recent' => (clone $attempts)
                ->with('user:id,name,phone')
                ->latest()
                ->limit(15)
                ->get(['id', 'user_id', 'mission_id', 'description', 'created_at'])
                ->map(fn (ScoreLedgerEntry $entry) => [
                    'id' => $entry->id,
                    'user' => $entry->user?->name,
                    'phone' => $entry->user?->phone,
                    'mission_id' => $entry->mission_id,
                    'description' => $entry->description,
                    'created_at' => optional($entry->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referent(): array
    {
        $blocked = Mission::where('referent_required', true)
            ->whereIn('status', self::REFERENT_BLOCKED_STATUSES);

        return [
            'blocked' => (clone $blocked)->count(),
            'threshold' => (int) config('prosartisan.mission.referent_threshold', 2000000),
            'recent' => (clone $blocked)
                ->with(['client:id,name', 'artisan:id,name'])
                ->latest()
                ->limit(15)
                ->get(['id', 'client_id', 'artisan_id', 'status', 'montant_total', 'created_at'])
                ->map(fn (Mission $mission) => [
                    'id' => $mission->id,
                    'client' => $mission->client?->name,
                    'artisan' => $mission->artisan?->name,
                    'status' => $mission->status,
                    'montant_total' => (int) $mission->montant_total,
                    'created_at' => optional($mission->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
