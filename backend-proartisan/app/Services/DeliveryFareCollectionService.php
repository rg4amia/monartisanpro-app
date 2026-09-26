<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Facades\Log;

/**
 * Recouvrement des courses livrées impayées (Chantier 11).
 *
 * À la livraison, le montant final de la course est révélé et demandé au
 * client (modèle « à la Yango »). Tant qu'il ne l'a pas réglé :
 * - il est relancé à intervalle régulier (notification en base, push, SMS) ;
 * - au-delà du plafond de relances, il est présumé avoir payé le livreur
 *   hors plateforme — infraction aux conditions d'utilisation — et son compte
 *   est restreint : plus de commande ni de mission, paiement toujours permis ;
 * - la restriction tombe d'elle-même au paiement de la dernière course due,
 *   ou sur décision d'un admin (auditée).
 */
class DeliveryFareCollectionService
{
    public function __construct(
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
    ) {}

    public function reminderIntervalHours(): int
    {
        return max(1, (int) Setting::getValueByKey('delivery_fare_reminder_interval_hours', 24));
    }

    public function maxReminders(): int
    {
        return max(1, (int) Setting::getValueByKey('delivery_fare_reminder_max', 5));
    }

    /**
     * Relances arrivées à échéance et restrictions au-delà du plafond.
     *
     * @return array{reminded: int, restricted: int}
     */
    public function processDue(): array
    {
        $interval = $this->reminderIntervalHours();
        $max = $this->maxReminders();
        $reminded = 0;
        $restricted = 0;

        $orders = $this->unpaidQuery()->with('client')->orderBy('id')->limit(500)->get();

        foreach ($orders as $order) {
            $since = $order->delivery_fare_last_reminder_at ?? $order->delivered_at ?? $order->updated_at;
            if ($since && $since->gt(now()->subHours($interval))) {
                continue;
            }

            try {
                if ($order->delivery_fare_reminders_count < $max) {
                    $this->remind($order);
                    $reminded++;
                } elseif ($order->client && ! $order->client->isPaymentRestricted()) {
                    $this->restrict($order->client, $order);
                    $restricted++;
                }
            } catch (\Throwable $e) {
                Log::error("[Course impayée] Traitement impossible pour la commande #{$order->id} : {$e->getMessage()}");
            }
        }

        return ['reminded' => $reminded, 'restricted' => $restricted];
    }

    /**
     * Relance le client d'une course impayée (automatique ou par un admin).
     */
    public function remind(Order $order, ?User $admin = null): Order
    {
        if ($order->status !== 'delivered' || $order->delivery_fare_status !== 'a_payer') {
            throw new \InvalidArgumentException('Cette course ne reste pas à régler.');
        }

        $count = (int) $order->delivery_fare_reminders_count + 1;
        $max = $this->maxReminders();

        $order->update([
            'delivery_fare_reminders_count' => $count,
            'delivery_fare_last_reminder_at' => now(),
        ]);

        $amount = number_format($order->deliveryFareDue(), 0, ',', ' ').' FCFA';
        $warning = $count >= $max
            ? 'Dernier rappel : sans paiement, votre compte sera restreint.'
            : "Au-delà de {$max} rappels, votre compte sera restreint.";

        if ($order->client) {
            $this->notifications->send(
                $order->client,
                'payment',
                "Course à régler — rappel {$count}/{$max}",
                "La course de votre commande #{$order->id} ({$amount}) reste à régler depuis l'application (Wave ou Orange Money). Régler une course en dehors de ProsArtisan enfreint les conditions d'utilisation. {$warning}",
                ['order_id' => $order->id, 'action' => 'pay_delivery_fare']
            );
        }

        if ($admin) {
            $this->audit->log('delivery_fare.reminded', $order, [
                'reminder' => $count,
                'montant' => $order->deliveryFareDue(),
            ], "Commande #{$order->id}", $admin);
        }

        return $order->fresh();
    }

    /**
     * Restreint le client : plus de commande ni de mission tant qu'une course
     * reste impayée.
     */
    public function restrict(User $client, Order $order): void
    {
        $reason = "Course de la commande #{$order->id} impayée après {$order->delivery_fare_reminders_count} relances : paiement présumé hors plateforme, en infraction aux conditions d'utilisation.";

        $client->update([
            'payment_restricted_at' => now(),
            'payment_restriction_reason' => $reason,
        ]);

        $amount = number_format($order->deliveryFareDue(), 0, ',', ' ').' FCFA';

        $this->notifications->send(
            $client,
            'payment',
            'Compte restreint',
            "La course de votre commande #{$order->id} ({$amount}) n'a pas été réglée malgré nos rappels. Vous ne pouvez plus commander ni publier de mission jusqu'à son paiement depuis l'application.",
            ['order_id' => $order->id, 'action' => 'pay_delivery_fare']
        );

        try {
            // Type non critique : notification et push aux admins, sans SMS.
            $this->notifications->sendAdmin(
                'delivery_fare_unpaid',
                'Client restreint pour course impayée',
                "{$client->name} (#{$client->id}) : course de la commande #{$order->id} ({$amount}) impayée après {$order->delivery_fare_reminders_count} relances.",
                ['order_id' => $order->id, 'user_id' => $client->id]
            );
        } catch (\Throwable $e) {
            Log::warning('[Course impayée] Alerte admin non envoyée : '.$e->getMessage());
        }

        $this->audit->log('client.payment_restricted', $client, [
            'order_id' => $order->id,
            'reminders' => $order->delivery_fare_reminders_count,
            'reason' => $reason,
        ], $client->name, null);
    }

    /**
     * Lève la restriction dès qu'aucune course ne reste due.
     */
    public function liftIfSettled(User $client): bool
    {
        if (! $client->isPaymentRestricted() || $this->unpaidQuery()->where('client_id', $client->id)->exists()) {
            return false;
        }

        $client->update(['payment_restricted_at' => null, 'payment_restriction_reason' => null]);

        $this->notifications->send(
            $client,
            'payment',
            'Restriction levée',
            'Merci pour votre paiement. Vous pouvez de nouveau commander et publier des missions.'
        );

        return true;
    }

    /**
     * Levée par un admin (geste commercial, erreur, règlement constaté).
     */
    public function liftByAdmin(User $client, User $admin, string $reason): void
    {
        if (! $client->isPaymentRestricted()) {
            throw new \InvalidArgumentException("Ce compte n'est pas restreint.");
        }

        $previous = $client->payment_restriction_reason;
        $client->update(['payment_restricted_at' => null, 'payment_restriction_reason' => null]);

        $this->audit->log('client.payment_restriction_lifted', $client, [
            'reason' => $reason,
            'previous_reason' => $previous,
        ], $client->name, $admin);

        $this->notifications->send(
            $client,
            'payment',
            'Restriction levée',
            'La restriction de votre compte a été levée par le support ProsArtisan.'
        );
    }

    /**
     * Commandes dont la course reste due, pour le message de refus.
     *
     * @return list<int>
     */
    public function unpaidOrderIds(User $client): array
    {
        return $this->unpaidQuery()->where('client_id', $client->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Vue backoffice : courses impayées et comptes restreints.
     */
    public function adminOverview(): array
    {
        $interval = $this->reminderIntervalHours();
        $max = $this->maxReminders();

        $unpaid = $this->unpaidQuery()
            ->with(['client:id,name,phone,payment_restricted_at', 'driver:id,name,phone'])
            ->orderBy('delivered_at')
            ->limit(200)
            ->get()
            ->map(function (Order $order) use ($interval) {
                $since = $order->delivery_fare_last_reminder_at ?? $order->delivered_at ?? $order->updated_at;

                return [
                    'order_id' => $order->id,
                    'client' => $order->client ? ['id' => $order->client->id, 'name' => $order->client->name, 'phone' => $order->client->phone, 'restricted' => $order->client->isPaymentRestricted()] : null,
                    'driver' => $order->driver ? ['id' => $order->driver->id, 'name' => $order->driver->name] : null,
                    'montant' => $order->deliveryFareDue(),
                    'delivered_at' => $order->delivered_at?->toIso8601String(),
                    'reminders_count' => (int) $order->delivery_fare_reminders_count,
                    'last_reminder_at' => $order->delivery_fare_last_reminder_at?->toIso8601String(),
                    'next_reminder_at' => $since?->copy()->addHours($interval)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $restricted = User::whereNotNull('payment_restricted_at')
            ->orderByDesc('payment_restricted_at')
            ->limit(100)
            ->get(['id', 'name', 'phone', 'payment_restricted_at', 'payment_restriction_reason'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'restricted_at' => $user->payment_restricted_at?->toIso8601String(),
                'reason' => $user->payment_restriction_reason,
            ])
            ->values()
            ->all();

        return [
            'unpaid' => $unpaid,
            'restricted' => $restricted,
            'settings' => ['interval_hours' => $interval, 'max_reminders' => $max],
            'stats' => [
                'unpaid_count' => count($unpaid),
                'unpaid_amount' => array_sum(array_column($unpaid, 'montant')),
                'restricted_count' => User::whereNotNull('payment_restricted_at')->count(),
            ],
        ];
    }

    private function unpaidQuery()
    {
        return Order::where('status', 'delivered')->where('delivery_fare_status', 'a_payer');
    }
}
