<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Models\DriverCashout;
use App\Models\MobileMoneyPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Retrait des gains d'un livreur, sur le modèle du cash-out quincaillerie
 * (`SupplierCashoutService`) : demande par le livreur, approbation puis
 * versement par l'administrateur, ou rejet motivé.
 *
 * Différence assumée avec le cash-out quincaillerie : le versement Mobile
 * Money est réellement émis par `MobileMoneyPayoutService` (relance en cas
 * d'échec, historique), et le portefeuille n'est débité qu'au virement
 * réussi. Seul le virement bancaire se solde manuellement, sur référence.
 */
class DriverCashoutService
{
    public const MINIMUM_AMOUNT = 500;

    public function __construct(
        private MobileMoneyPayoutService $payouts,
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * Solde retirable : gains au ledger moins les retraits déjà réservés.
     */
    public function getAvailableBalance(User $driver): int
    {
        $wallet = $driver->getWalletBalance(WalletType::WALLET_MO);

        $reserved = (int) DriverCashout::where('driver_id', $driver->id)
            ->whereIn('statut', DriverCashout::STATUTS_RESERVES)
            ->sum('montant_brut');

        return max(0, $wallet - $reserved);
    }

    public function commissionRate(): float
    {
        return max(0.0, (float) Setting::getValueByKey('commission_cashout_livreur', 0.0));
    }

    public function stats(User $driver): array
    {
        return [
            'wallet_mo' => $driver->getWalletBalance(WalletType::WALLET_MO),
            'available_balance' => $this->getAvailableBalance($driver),
            'pending_amount' => (int) DriverCashout::where('driver_id', $driver->id)
                ->whereIn('statut', DriverCashout::STATUTS_RESERVES)
                ->sum('montant_brut'),
            'total_withdrawn' => (int) DriverCashout::where('driver_id', $driver->id)
                ->where('statut', DriverCashout::STATUT_COMPLETE)
                ->sum('montant_net'),
            'commission_rate' => $this->commissionRate(),
            'minimum_amount' => self::MINIMUM_AMOUNT,
        ];
    }

    public function requestCashout(User $driver, array $data): DriverCashout
    {
        if (! $driver->isLivreur()) {
            throw new \InvalidArgumentException('Seul un livreur peut retirer des gains de course.');
        }

        if ($driver->kyc_status !== 'actif') {
            throw new \InvalidArgumentException('Votre compte doit être vérifié (KYC) pour effectuer un retrait.');
        }

        $mode = $data['mode_retrait'] ?? DriverCashout::MODE_WAVE;
        if (! in_array($mode, DriverCashout::MODES, true)) {
            throw new \InvalidArgumentException('Mode de retrait non supporté.');
        }

        if ($mode === DriverCashout::MODE_VIREMENT_BANCAIRE && (empty($data['bank_name']) || empty($data['bank_account_number']))) {
            throw new \InvalidArgumentException('La banque et le numéro de compte sont requis pour un virement bancaire.');
        }

        $montantBrut = (int) ($data['montant_brut'] ?? 0);
        if ($montantBrut < self::MINIMUM_AMOUNT) {
            throw new \InvalidArgumentException('Le montant minimum de retrait est de '.number_format(self::MINIMUM_AMOUNT, 0, ',', ' ').' FCFA.');
        }

        return DB::transaction(function () use ($driver, $data, $mode, $montantBrut) {
            // Verrou sur le livreur : deux demandes simultanées ne doivent pas
            // réserver deux fois le même solde (plafond cumulatif, Règle d'or 36).
            User::lockForUpdate()->find($driver->id);

            $available = $this->getAvailableBalance($driver);
            if ($montantBrut > $available) {
                throw new \InvalidArgumentException('Solde disponible insuffisant ('.number_format($available, 0, ',', ' ').' FCFA disponibles).');
            }

            $rate = $this->commissionRate();
            $commission = (int) round($montantBrut * $rate);

            $cashout = DriverCashout::create([
                'reference' => DriverCashout::generateReference(),
                'driver_id' => $driver->id,
                'beneficiary_name' => $data['beneficiary_name'] ?? $driver->name ?? $driver->phone,
                'beneficiary_phone' => $data['beneficiary_phone'] ?? $driver->payment_phone ?? $driver->phone,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'montant_brut' => $montantBrut,
                'commission_rate' => $rate,
                'montant_commission' => $commission,
                'montant_net' => max(0, $montantBrut - $commission),
                'statut' => DriverCashout::STATUT_EN_ATTENTE,
                'mode_retrait' => $mode,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('driver_cashout.requested', $cashout, [
                'montant_brut' => $montantBrut,
                'mode_retrait' => $mode,
            ], $cashout->reference, $driver);

            return $cashout;
        });
    }

    public function approve(DriverCashout $cashout, User $admin): DriverCashout
    {
        if ($cashout->statut !== DriverCashout::STATUT_EN_ATTENTE) {
            throw new \InvalidArgumentException('Seule une demande en attente peut être approuvée.');
        }

        $cashout->update([
            'statut' => DriverCashout::STATUT_APPROUVE,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $this->audit->log('driver_cashout.approved', $cashout, [], $cashout->reference, $admin);

        return $cashout;
    }

    /**
     * Verse le retrait. Mobile Money : virement émis par le module de
     * versements (le retrait passe à `complete` au virement réussi, reste
     * `approuve` et relançable sinon). Virement bancaire : soldé sur la
     * référence bancaire fournie par l'administrateur.
     */
    public function pay(DriverCashout $cashout, User $admin, ?string $externalReference = null): DriverCashout
    {
        return DB::transaction(function () use ($cashout, $admin, $externalReference) {
            $cashout = DriverCashout::lockForUpdate()->findOrFail($cashout->id);

            if ($cashout->statut === DriverCashout::STATUT_EN_ATTENTE) {
                $this->approve($cashout, $admin);
            }

            if ($cashout->statut !== DriverCashout::STATUT_APPROUVE) {
                throw new \InvalidArgumentException('Cette demande de retrait ne peut pas être versée.');
            }

            if ($cashout->payout_id) {
                throw new \InvalidArgumentException('Un versement est déjà engagé pour ce retrait : relancez-le depuis la liste des versements.');
            }

            if ($cashout->mode_retrait === DriverCashout::MODE_VIREMENT_BANCAIRE && ! $externalReference) {
                throw new \InvalidArgumentException('La référence du virement bancaire est requise.');
            }

            $driver = $cashout->driver;
            $provider = $cashout->mode_retrait === DriverCashout::MODE_ORANGE_MONEY ? 'orange_money' : 'wave';

            // Le débit porte sur le montant brut ; la commission éventuelle
            // rejoint le compte ProsArtisan au versement effectif.
            $isBankTransfer = $cashout->mode_retrait === DriverCashout::MODE_VIREMENT_BANCAIRE;

            $payout = $this->payouts->dispatch(
                $driver,
                WalletType::WALLET_MO,
                $cashout->montant_brut,
                MobileMoneyPayout::CONTEXT_RETRAIT_LIVREUR,
                $isBankTransfer ? 'virement_bancaire' : $provider,
                "Retrait des gains livreur {$cashout->reference}",
                [
                    'type' => 'paiement_livreur',
                    'wallet_source' => 'driver_wallet_'.$driver->id,
                    'wallet_dest' => 'driver_'.$cashout->mode_retrait.'_'.$driver->id,
                    'metadata' => [
                        'driver_cashout_id' => $cashout->id,
                        'reference' => $cashout->reference,
                        'montant_brut' => $cashout->montant_brut,
                        'montant_commission' => $cashout->montant_commission,
                        'montant_net' => $cashout->montant_net,
                    ],
                ],
                [
                    'type' => 'driver_cashout',
                    'driver_cashout_id' => $cashout->id,
                    'reference' => $cashout->reference,
                ],
                $cashout->montant_net,
                $cashout->beneficiary_phone,
                attempt: false,
            );

            $cashout->update(['payout_id' => $payout->id]);

            $payout = $isBankTransfer
                ? $this->payouts->markPaidManually($payout, $admin, $externalReference, 'Virement bancaire du retrait livreur')
                : $this->payouts->attemptNow($payout, $admin);

            $this->audit->log('driver_cashout.paid', $cashout, [
                'payout_reference' => $payout->reference,
                'payout_statut' => $payout->statut,
            ], $cashout->reference, $admin);

            return $this->syncWithPayout($cashout->fresh());
        });
    }

    /**
     * Répercute l'issue du versement sur le retrait (appelé après chaque
     * tentative, y compris les relances automatiques).
     */
    public function syncWithPayout(DriverCashout $cashout): DriverCashout
    {
        $payout = $cashout->payout()->first();

        if ($payout && $payout->isPaid() && $cashout->statut !== DriverCashout::STATUT_COMPLETE) {
            $cashout->update([
                'statut' => DriverCashout::STATUT_COMPLETE,
                'processed_at' => now(),
            ]);

            if ($cashout->montant_commission > 0) {
                app(WalletService::class)->creditPlatformFinancialAccount(
                    $cashout->montant_commission,
                    "Frais de retrait livreur {$cashout->reference}",
                    ['driver_cashout_id' => $cashout->id]
                );
            }

            try {
                $this->notifications->send(
                    $cashout->driver,
                    'payment',
                    'Retrait versé',
                    'Votre retrait '.$cashout->reference.' de '.number_format($cashout->montant_net, 0, ',', ' ').' FCFA a été versé.'
                );
            } catch (\Throwable) {
                // Notification best-effort : le versement est acquis.
            }
        }

        return $cashout->fresh();
    }

    public function reject(DriverCashout $cashout, User $admin, string $reason): DriverCashout
    {
        return DB::transaction(function () use ($cashout, $admin, $reason) {
            $cashout = DriverCashout::lockForUpdate()->findOrFail($cashout->id);

            if (! in_array($cashout->statut, DriverCashout::STATUTS_RESERVES, true)) {
                throw new \InvalidArgumentException('Ce retrait ne peut plus être rejeté.');
            }

            if ($cashout->payout_id) {
                $payout = $cashout->payout;
                if ($payout?->isPaid()) {
                    throw new \InvalidArgumentException('Ce retrait a déjà été versé.');
                }
                if ($payout) {
                    $this->payouts->cancel($payout, $admin, $reason);
                }
            }

            $cashout->update([
                'statut' => DriverCashout::STATUT_REJETE,
                'notes' => trim(($cashout->notes ? $cashout->notes."\n" : '').'Rejet : '.$reason),
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            $this->audit->log('driver_cashout.rejected', $cashout, ['reason' => $reason], $cashout->reference, $admin);

            try {
                $this->notifications->send(
                    $cashout->driver,
                    'payment',
                    'Retrait refusé',
                    "Votre demande de retrait {$cashout->reference} a été refusée : {$reason}. Le montant reste disponible sur votre portefeuille."
                );
            } catch (\Throwable) {
            }

            return $cashout->fresh();
        });
    }

    public function listFor(User $driver, int $limit = 50): array
    {
        return DriverCashout::with('payout')
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (DriverCashout $cashout) => $this->present($cashout))
            ->values()
            ->all();
    }

    /**
     * Vue backoffice : demandes à traiter puis dernières demandes traitées.
     */
    public function adminOverview(): array
    {
        $map = fn (DriverCashout $cashout) => $this->present($cashout) + [
            'driver' => $cashout->driver ? [
                'id' => $cashout->driver->id,
                'name' => $cashout->driver->name,
                'phone' => $cashout->driver->phone,
            ] : null,
            'available_balance' => $cashout->driver ? $this->getAvailableBalance($cashout->driver) : 0,
        ];

        return [
            'pending' => DriverCashout::with(['driver:id,name,phone', 'payout'])
                ->whereIn('statut', DriverCashout::STATUTS_RESERVES)
                ->oldest('id')
                ->limit(100)
                ->get()
                ->map($map)
                ->values()
                ->all(),
            'recent' => DriverCashout::with(['driver:id,name,phone', 'payout'])
                ->whereIn('statut', [DriverCashout::STATUT_COMPLETE, DriverCashout::STATUT_REJETE])
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map($map)
                ->values()
                ->all(),
            'commission_rate' => $this->commissionRate(),
        ];
    }

    public function present(DriverCashout $cashout): array
    {
        $payout = $cashout->relationLoaded('payout') ? $cashout->payout : $cashout->payout()->first();

        return [
            'id' => $cashout->id,
            'reference' => $cashout->reference,
            'montant_brut' => $cashout->montant_brut,
            'montant_commission' => $cashout->montant_commission,
            'montant_net' => $cashout->montant_net,
            'statut' => $cashout->statut,
            'statut_label' => $cashout->statutLabel(),
            'mode_retrait' => $cashout->mode_retrait,
            'beneficiary_name' => $cashout->beneficiary_name,
            'beneficiary_phone' => $cashout->beneficiary_phone,
            'notes' => $cashout->notes,
            'payout' => $payout ? $this->payouts->present($payout, false) : null,
            // Transaction du versement : son reçu PDF est disponible une fois
            // le retrait versé (`/transactions/{id}/receipt-link`).
            'transaction_id' => $payout?->transaction_id,
            'receipt_available' => $payout?->isPaid() ?? false,
            'created_at' => $cashout->created_at?->toIso8601String(),
            'processed_at' => $cashout->processed_at?->toIso8601String(),
        ];
    }
}
