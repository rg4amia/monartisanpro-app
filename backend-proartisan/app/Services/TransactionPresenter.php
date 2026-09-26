<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Transaction;
use App\Models\User;

/**
 * Sens et libellé d'une transaction du point de vue de l'utilisateur qui la
 * consulte.
 *
 * Le mobile déduisait le signe du seul `type` : un `acompte` s'affichait en
 * « + » chez le client qui venait de le payer. Le sens se juge désormais sur
 * les comptes source et destination : `entrant` si l'argent arrive sur un
 * compte du lecteur, `sortant` s'il en part, `sequestre` pour des fonds
 * consignés par un tiers sur une mission du lecteur (visibles mais pas
 * encore à lui).
 */
class TransactionPresenter
{
    public const ENTRANT = 'entrant';

    public const SORTANT = 'sortant';

    public const SEQUESTRE = 'sequestre';

    /**
     * Comptes personnels dont le suffixe numérique est l'identifiant de
     * l'utilisateur. Liste fermée : `artisan_mo_jalon_{id}` ou
     * `escrow_mission_{id}` portent un autre identifiant et ne doivent pas
     * être lus comme un utilisateur.
     */
    private const PERSONAL_ACCOUNT_PATTERN = '/^(?:client_mobile_money|client_bank|artisan_mobile_money|driver_wallet|supplier_wallet|supplier_(?:wave|orange_money|virement_bancaire|especes_guichet)|driver_(?:wave|orange_money|virement_bancaire))_(\d+)$/';

    private const STATUT_LABELS = [
        'en_attente' => 'En attente',
        'confirme' => 'Confirmé',
        'echoue' => 'Échoué',
    ];

    /**
     * @return array{direction: string, libelle: string, statut_libelle: string, montant_signe: int}
     */
    public function describe(Transaction $transaction, ?User $viewer): array
    {
        $direction = $this->direction($transaction, $viewer);
        $statut = $transaction->statut instanceof PaymentStatus ? $transaction->statut->value : (string) $transaction->statut;
        $montant = (int) $transaction->montant;

        $statutLabel = self::STATUT_LABELS[$statut] ?? $statut;
        if ($statut === 'echoue' && isset($transaction->metadata['payout_id'])) {
            $statutLabel = 'Virement échoué — relance prévue';
        }

        return [
            'direction' => $direction,
            'libelle' => $this->label($transaction, $direction),
            'statut_libelle' => $statutLabel,
            'montant_signe' => match ($direction) {
                self::ENTRANT => $montant,
                self::SORTANT => -$montant,
                default => 0,
            },
        ];
    }

    public function direction(Transaction $transaction, ?User $viewer): string
    {
        if (! $viewer) {
            return self::SEQUESTRE;
        }

        // Retrait de ses propres gains vers Mobile Money ou banque : le solde
        // du portefeuille baisse, c'est une sortie (livreur comme quincaillerie).
        if ((int) $transaction->user_id === (int) $viewer->id && $this->isWithdrawal($transaction)) {
            return self::SORTANT;
        }

        if ($this->accountOwner($transaction->wallet_dest) === $viewer->id) {
            return self::ENTRANT;
        }

        if ($this->accountOwner($transaction->wallet_source) === $viewer->id) {
            return self::SORTANT;
        }

        // Paiement d'un tiers consigné sur une mission du lecteur (acompte du
        // client vu par l'artisan) : des fonds sécurisés, pas un revenu.
        if ((int) $transaction->user_id !== (int) $viewer->id) {
            return self::SEQUESTRE;
        }

        return match ($transaction->type) {
            'acompte', 'paiement_livraison', 'paiement_livreur' => self::SORTANT,
            'remboursement' => str_starts_with((string) $transaction->wallet_source, 'escrow') ? self::ENTRANT : self::SORTANT,
            default => self::ENTRANT,
        };
    }

    private function isWithdrawal(Transaction $transaction): bool
    {
        return $transaction->type === 'paiement_livreur'
            || ($transaction->type === 'paiement_fournisseur' && $transaction->wallet_source === 'platform_treasury');
    }

    private function accountOwner(?string $account): ?int
    {
        if ($account && preg_match(self::PERSONAL_ACCOUNT_PATTERN, $account, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function label(Transaction $transaction, string $direction): string
    {
        $dest = (string) $transaction->wallet_dest;
        $source = (string) $transaction->wallet_source;

        return match ($transaction->type) {
            'acompte' => match (true) {
                $direction === self::SEQUESTRE => 'Fonds sécurisés par le client',
                str_starts_with($dest, 'escrow_order') || str_starts_with($dest, 'escrow_group') => 'Paiement de commande de matériaux',
                default => 'Paiement au coffre de sécurité',
            },
            'paiement_livraison' => 'Paiement de la course de livraison',
            'liberation_jalon' => str_starts_with($dest, 'driver_wallet') ? 'Gain de course livrée' : 'Paiement d\'étape de chantier',
            'paiement_fournisseur' => $source === 'platform_treasury' ? 'Retrait de gains vers Mobile Money' : 'Vente de matériaux',
            'paiement_livreur' => 'Retrait de gains vers Mobile Money',
            'remboursement' => $direction === self::SORTANT ? 'Remboursement du micro-crédit' : 'Remboursement reçu',
            'credit' => str_starts_with($source, 'microfinance') || $source === 'wave_ci' ? 'Micro-crédit versé' : 'Crédit reçu',
            default => 'Opération',
        };
    }
}
