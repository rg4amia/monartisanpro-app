<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\CreditApplication;
use App\Models\DriverCashout;
use App\Models\Mission;
use App\Models\MobileMoneyPayout;
use App\Models\Order;
use App\Models\OrderDisputeDebt;
use App\Models\SupplierCashout;
use App\Models\User;

/**
 * Ce qui engage encore un compte : fonds, travaux, commandes, dettes
 * (Chantier 27, lot E).
 *
 * Un compte engagé ne se supprime pas, ne s'anonymise pas et ne change pas de
 * rôle : ses fonds et ses chantiers resteraient sans titulaire joignable, ou
 * attachés à un rôle qui ne les voit plus.
 */
class AccountEngagementService
{
    /** États d'une mission encore à mener à son terme. */
    public const ONGOING_MISSION_STATES = [
        'pending_artisan_acceptance', 'pending_funding', 'funded_locked',
        'in_progress', 'pending_approval', 'disputed',
    ];

    /** États d'une commande payée et pas encore close. */
    public const ONGOING_ORDER_STATES = [
        'paid', 'prepared', 'searching_driver', 'driver_assigned',
        'driver_picked_up', 'shipping', 'disputed',
    ];

    /**
     * Motifs, en français, qui s'opposent à la fermeture ou au changement de
     * rôle du compte. Une liste vide signifie que le compte est libre.
     *
     * @return array<int, string>
     */
    public function blockers(User $user): array
    {
        $blockers = [];

        $balance = $user->getWalletBalance(WalletType::WALLET_MO) + $user->getWalletBalance(WalletType::WALLET_MATERIAUX);
        if ($balance > 0) {
            $blockers[] = 'un solde de '.number_format($balance, 0, ',', ' ').' FCFA en portefeuille';
        }

        $missions = $this->ongoingMissionsCount($user);
        if ($missions > 0) {
            $blockers[] = $missions === 1 ? 'une mission en cours' : "{$missions} missions en cours";
        }

        $orders = Order::query()
            ->whereIn('status', self::ONGOING_ORDER_STATES)
            ->where(fn ($q) => $q->where('client_id', $user->id)
                ->orWhere('supplier_id', $user->id)
                ->orWhere('driver_id', $user->id))
            ->count();
        if ($orders > 0) {
            $blockers[] = $orders === 1 ? 'une commande en cours' : "{$orders} commandes en cours";
        }

        if ($user->isPaymentRestricted()) {
            $blockers[] = 'une course de livraison à régler';
        }

        if (CreditApplication::active()->where('user_id', $user->id)->exists()) {
            $blockers[] = 'un micro-crédit en cours';
        }

        if (OrderDisputeDebt::where('user_id', $user->id)->where('statut', OrderDisputeDebt::STATUT_EN_COURS)->exists()) {
            $blockers[] = 'une dette de litige à régler';
        }

        $payouts = MobileMoneyPayout::query()
            ->whereIn('statut', MobileMoneyPayout::STATUTS_NON_ABOUTIS)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('source_user_id', $user->id))
            ->exists();
        if ($payouts) {
            $blockers[] = 'un versement en attente';
        }

        $cashouts = SupplierCashout::where('supplier_id', $user->id)
            ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
            ->exists()
            || DriverCashout::where('driver_id', $user->id)
                ->whereIn('statut', DriverCashout::STATUTS_RESERVES)
                ->exists();
        if ($cashouts) {
            $blockers[] = 'un retrait en attente';
        }

        return $blockers;
    }

    public function ongoingMissionsCount(User $user): int
    {
        return Mission::query()
            ->whereIn('status', self::ONGOING_MISSION_STATES)
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('artisan_id', $user->id))
            ->count();
    }

    /**
     * @param  string  $action  Ce qui est refusé, à l'infinitif : « être supprimé », « changer de rôle ».
     *
     * @throws \LogicException Message listant ce qui bloque.
     */
    public function assertFree(User $user, string $action): void
    {
        $blockers = $this->blockers($user);

        if ($blockers !== []) {
            throw new \LogicException("Ce compte ne peut pas {$action} : il a encore ".$this->sentence($blockers).'.');
        }
    }

    /** @param  array<int, string>  $items */
    private function sentence(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' et '.$last;
    }
}
