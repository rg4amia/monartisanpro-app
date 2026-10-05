<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;

/**
 * Levée, par un administrateur, de la suspension qui frappe la saisie d'un
 * code de retrait ou de réception après trop de codes faux (Chantier 34).
 *
 * La suspension se lève seule à son terme. Cette levée sert le cas où le
 * support a vérifié la situation — un fournisseur ou un client qui s'est
 * trompé — et où attendre bloque une remise en cours.
 */
class OrderCodeSuspensionAdminService
{
    /** Codes concernés, et leur libellé français. */
    public const KINDS = ['pickup' => 'retrait', 'reception' => 'réception'];

    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * @param  'pickup'|'reception'  $kind
     *
     * @throws \InvalidArgumentException si aucune suspension n'est en cours
     */
    public function lift(Order $order, string $kind, User $admin, string $reason): void
    {
        $lockColumn = "{$kind}_code_locked_until";
        $attemptsColumn = "{$kind}_code_attempts";
        $lockedUntil = $order->{$lockColumn};

        if ($lockedUntil === null || $lockedUntil->isPast()) {
            throw new \InvalidArgumentException('La saisie de ce code n\'est pas suspendue.');
        }

        $attempts = (int) $order->{$attemptsColumn};

        // Le compteur repart de zéro : la prochaine série de codes faux
        // reprend à la première durée de suspension.
        $order->forceFill([$attemptsColumn => 0, $lockColumn => null])->saveQuietly();

        $this->audit->log('order.code_suspension.lifted', $order, [
            'code' => self::KINDS[$kind],
            'codes_faux' => $attempts,
            'suspendu_jusqu_a' => $lockedUntil->toIso8601String(),
            'motif' => $reason,
        ], "Commande #{$order->id}", $admin);
    }
}
