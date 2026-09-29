<?php

namespace App\Services\Admin;

use App\Models\FraudAlert;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\ScoreLedgerEntry;
use App\Models\Transaction;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chantier C7 (P2-12) — santé opérationnelle du backoffice.
 *
 * Agrège cinq signaux critiques : files d'attente en échec, webhooks de
 * paiement KO, tentatives de fraude GPS J-Code (> 100 m), missions bloquées
 * au seuil Référent (> 2 000 000 FCFA) et envois push/SMS échoués.
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
            'notifications' => $this->notifications(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Compteurs critiques uniquement (pour la décision d'alerte Telegram).
     *
     * @return array{failed_jobs: int, failed_payments_24h: int, gps_fraud_7d: int, referent_blocked: int, failed_notifications_24h: int}
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
            'failed_notifications_24h' => $this->failedNotifications()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * Envois push et SMS échoués (Chantier 14, lot B). Un canal ignoré
     * (OneSignal non configuré, numéro absent) n'est pas une panne.
     *
     * @return array<string, mixed>
     */
    private function notifications(): array
    {
        return [
            'failed_24h' => $this->failedNotifications()->where('created_at', '>=', now()->subDay())->count(),
            'sent_24h' => NotificationDelivery::where('status', NotificationDelivery::STATUS_SENT)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'recent' => $this->failedNotifications()
                ->latest('created_at')
                ->limit(15)
                ->get(['id', 'event_key', 'channel', 'provider', 'reason', 'created_at'])
                ->map(fn (NotificationDelivery $delivery) => [
                    'id' => $delivery->id,
                    'event' => NotificationCatalog::has((string) $delivery->event_key)
                        ? NotificationCatalog::get($delivery->event_key)['label']
                        : $delivery->event_key,
                    'channel' => $delivery->channel,
                    'provider' => $delivery->provider,
                    'reason' => $delivery->reason,
                    'created_at' => optional($delivery->created_at)->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function failedNotifications(): Builder
    {
        return NotificationDelivery::where('status', NotificationDelivery::STATUS_FAILED);
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

        $fraudAlerts = FraudAlert::query();
        $openAlertsCount = (clone $fraudAlerts)->ouvertes()->count();
        $criticalAlertsCount = (clone $fraudAlerts)->critiques()->ouvertes()->count();
        $paymentHoldsCount = (clone $fraudAlerts)->where('action_taken', 'payment_hold')->whereIn('statut', ['ouverte', 'en_analyse'])->count();

        $alertsList = (clone $fraudAlerts)
            ->with(['user:id,name,phone,role', 'targetUser:id,name,phone,role', 'mission:id,montant_total'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (FraudAlert $a) => [
                'id' => $a->id,
                'reference' => $a->reference,
                'mission_id' => $a->mission_id,
                'user_name' => $a->user?->name ?? 'Inconnu',
                'user_phone' => $a->user?->phone,
                'user_role' => $a->user?->role,
                'target_name' => $a->targetUser?->name,
                'target_phone' => $a->targetUser?->phone,
                'target_role' => $a->targetUser?->role,
                'type' => $a->type,
                'severity' => $a->severity,
                'risk_score' => $a->risk_score,
                'statut' => $a->statut,
                'action_taken' => $a->action_taken,
                'reasons' => $a->reasons_json ?? [],
                'metadata' => $a->metadata_json ?? [],
                'created_at' => optional($a->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'gps_attempts_7d' => (clone $attempts)->where('created_at', '>=', now()->subDays(7))->count(),
            'gps_attempts_total' => (clone $attempts)->count(),
            'unread_alerts' => Notification::whereNull('read_at')->where('type', 'alert')->count(),
            'open_alerts_count' => $openAlertsCount,
            'critical_alerts_count' => $criticalAlertsCount,
            'payment_holds_count' => $paymentHoldsCount,
            'alerts_list' => $alertsList,
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
