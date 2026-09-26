<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Initiation et suivi des paiements client (acompte de devis, jalon hybride)
 * via Wave, Orange Money ou virement bancaire.
 *
 * Les méthodes d'initiation renvoient `['message' => string, 'data' => array]`
 * et lèvent PaymentException pour tout refus métier.
 */
class PaymentService
{
    public function __construct(
        private WaveService $waveService,
        private OrangeMoneyService $orangeMoneyService,
        private WalletService $walletService,
        private DevisService $devisService,
        private BankTransferSettingsService $bankTransfer,
    ) {}

    /**
     * Initie le paiement de l'acompte d'un devis (intégral ou matériaux seuls).
     */
    public function initiateMissionPayment(
        User $client,
        Mission $mission,
        Devis $devis,
        int $montantDeclare,
        PaymentProvider $provider,
        string $paymentType,
        ?string $promoCode,
        ?string $phone,
    ): array {
        if ($mission->client_id !== $client->id) {
            throw new PaymentException('Vous n\'êtes pas autorisé à payer pour cette mission', 403);
        }

        if (! in_array((string) $mission->status, ['draft', 'pending_artisan_acceptance', 'pending_funding'], true)) {
            throw new PaymentException('Cette mission n\'est pas en attente de paiement (statut actuel: '.(string) $mission->status.')', 400);
        }

        if (! in_array($devis->statut, ['soumis', 'accepte'], true)) {
            throw new PaymentException('Ce devis ne peut pas être payé dans son état actuel.', 422);
        }

        $montantAttendu = $paymentType === 'hybrid' ? $devis->montant_materiaux : $devis->montant_total;

        if ($montantDeclare !== $montantAttendu) {
            Log::warning("Paiement initié: ajustement automatique du montant client ($montantDeclare FCFA) au montant officiel devis ($montantAttendu FCFA).");
        }

        [$discountAmount, $appliedPromoCode] = $this->resolvePromoDiscount($client, $promoCode, $montantAttendu);
        $montant = $montantAttendu - $discountAmount;

        // Plafond jugé sur le montant réellement encaissé, jamais sur le montant
        // déclaré par le client (ignoré ci-dessus) : sinon `montant: 100` suffisait
        // à régler un devis de plusieurs millions en Mobile Money.
        $this->assertPaymentAllowed($montant, $provider);

        $phone = (string) ($phone ?? $client->phone ?? '');
        $metadataMatch = ['devis_id' => $devis->id, 'payment_type' => $paymentType];

        $existing = $this->findPendingTransaction($mission, $client, $montant, $provider, $metadataMatch);
        if ($existing && ($reused = $this->reusePendingTransaction($existing, $provider, 'Paiement', ['devis_id' => $devis->id]))) {
            return $reused;
        }

        $transaction = $this->createPendingTransaction($mission, $client, $montant, $provider, $phone, [
            'mission_id' => $mission->id,
            'devis_id' => $devis->id,
            'payment_type' => $paymentType,
            'description' => $paymentType === 'hybrid' ? "Acompte matériaux mission #{$mission->id}" : "Acompte intégral mission #{$mission->id}",
            'promo_code' => $appliedPromoCode?->code,
            'discount_amount' => $discountAmount,
        ]);

        return $this->startCheckout(
            $transaction,
            $provider,
            $phone,
            "Acompte mission #{$mission->id}",
            $metadataMatch,
            'REF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'Instructions de virement bancaire pour acompte',
            'Paiement',
            ['devis_id' => $devis->id],
        );
    }

    /**
     * Initie le paiement d'un jalon individuel (projets hybrides uniquement).
     */
    public function initiateJalonPayment(User $client, Jalon $jalon, PaymentProvider $provider, ?string $phone): array
    {
        $mission = $jalon->mission;

        if ($mission->client_id !== $client->id) {
            throw new PaymentException('Vous n\'êtes pas autorisé à payer pour ce jalon.', 403);
        }

        if ($mission->payment_type !== 'hybrid') {
            throw new PaymentException('Ce jalon ne peut être payé individuellement que pour les projets hybrides.', 400);
        }

        if ($jalon->statut !== 'en_attente' && $jalon->statut !== 'soumis') {
            throw new PaymentException('Ce jalon n\'est pas en attente de paiement.', 422);
        }

        if ($this->walletService->isJalonFunded($jalon)) {
            throw new PaymentException('Ce jalon a déjà été payé et financé.', 422);
        }

        $montant = $jalon->montant;
        $this->assertPaymentAllowed($montant, $provider);

        $phone = (string) ($phone ?? $client->phone ?? '');
        $metadataMatch = ['jalon_id' => $jalon->id, 'payment_type' => 'jalon'];

        $existing = $this->findPendingTransaction($mission, $client, $montant, $provider, $metadataMatch);
        if ($existing && ($reused = $this->reusePendingTransaction($existing, $provider, 'Paiement du jalon', ['jalon_id' => $jalon->id]))) {
            return $reused;
        }

        $label = "Paiement jalon #{$jalon->ordre} mission #{$mission->id}";

        $transaction = $this->createPendingTransaction($mission, $client, $montant, $provider, $phone, [
            'mission_id' => $mission->id,
            'jalon_id' => $jalon->id,
            'payment_type' => 'jalon',
            'description' => $label,
        ]);

        return $this->startCheckout(
            $transaction,
            $provider,
            $phone,
            $label,
            $metadataMatch,
            'REF-JL-'.$jalon->id.'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'Instructions de virement bancaire pour jalon',
            'Paiement du jalon',
            ['jalon_id' => $jalon->id],
        );
    }

    /**
     * Initie le paiement de la course livrée (modèle « à la Yango ») : le
     * montant est celui révélé à la livraison (course + bonus d'attente),
     * jamais un montant posté par le client (Règle d'or 36). Seul le client de
     * la commande peut le régler.
     */
    public function initiateDeliveryFarePayment(User $client, Order $order, PaymentProvider $provider, ?string $phone): array
    {
        if ((int) $order->client_id !== (int) $client->id) {
            throw new PaymentException("Vous n'êtes pas autorisé à régler cette course.", 403);
        }

        if ($order->delivery_fare_status !== 'a_payer') {
            throw new PaymentException("Aucune course n'est à régler pour cette commande.", 422);
        }

        $montant = $order->deliveryFareDue();
        $this->assertPaymentAllowed($montant, $provider);

        $phone = (string) ($phone ?? $client->payment_phone ?? $client->phone ?? '');
        $metadataMatch = ['order_id' => $order->id, 'payment_type' => 'delivery_fare'];

        $existing = Transaction::where('user_id', $client->id)
            ->where('type', 'paiement_livraison')
            ->where('montant', $montant)
            ->where('provider', $provider)
            ->where('statut', PaymentStatus::EN_ATTENTE)
            ->whereJsonContains('metadata->order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if ($existing && ($reused = $this->reusePendingTransaction($existing, $provider, 'Paiement de la course', ['order_id' => $order->id]))) {
            return $reused;
        }

        $label = "Course de livraison commande #{$order->id}";

        $transaction = Transaction::create([
            'user_id' => $client->id,
            'type' => 'paiement_livraison',
            'montant' => $montant,
            'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_'.$client->id : 'client_mobile_money_'.$client->id,
            'wallet_dest' => 'escrow_order_'.$order->id,
            'provider' => $provider,
            'statut' => PaymentStatus::EN_ATTENTE,
            'client_phone' => $phone,
            'metadata' => [
                'order_id' => $order->id,
                'payment_type' => 'delivery_fare',
                'fare_total' => $order->deliveryFareTotal(),
                'waiting_bonus' => (int) $order->waiting_fee,
                'description' => $label,
            ],
        ]);

        return $this->startCheckout(
            $transaction,
            $provider,
            $phone,
            $label,
            $metadataMatch,
            'REF-LIV-'.$order->id.'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'Instructions de virement bancaire pour la course de livraison',
            'Paiement de la course',
            ['order_id' => $order->id],
        );
    }

    /**
     * Initie le paiement du séquestre d'un engagement de recrutement : les
     * journées en attente de paiement (lot initial ou prolongation). Leurs
     * identifiants sont figés sur la transaction, qui ne pourra débloquer
     * qu'elles (Règle d'or 36, plafond cumulatif).
     */
    public function initiateRecruitmentEngagementPayment(User $recruiter, RecruitmentEngagement $engagement, PaymentProvider $provider, ?string $phone): array
    {
        $workdays = $engagement->workdays()->where('status', 'awaiting_payment')->orderBy('day_number')->get();
        $montant = (int) $workdays->sum('montant');

        if ($montant <= 0) {
            throw new PaymentException('Aucun jour en attente de paiement pour cet engagement.');
        }

        $this->assertPaymentAllowed($montant, $provider);

        $transaction = $this->createRecruitmentTransaction($recruiter, 'recruitment_escrow', $montant, 'escrow_recruitment_'.$engagement->id, $provider, $phone, [
            'recruitment_engagement_id' => $engagement->id,
            'workday_ids' => $workdays->pluck('id')->all(),
            'description' => "Séquestre recrutement #{$engagement->id}",
        ]);

        return $this->startCheckout(
            $transaction,
            $provider,
            $transaction->client_phone,
            "Séquestre recrutement #{$engagement->id}",
            ['recruitment_engagement_id' => $engagement->id],
            $this->bankReference(),
            'Instructions de virement bancaire pour séquestre de recrutement',
            'Paiement',
            [],
        );
    }

    /**
     * Initie le paiement du séquestre d'accès aux candidatures d'une offre
     * (verrou anti-contournement payé avant toute consultation des postulants).
     */
    public function initiateApplicantsUnlockPayment(User $recruiter, RecruitmentOffer $offer, int $montant, PaymentProvider $provider, ?string $phone): array
    {
        $this->assertPaymentAllowed($montant, $provider);

        $label = "Séquestre d'accès aux candidatures — offre #{$offer->id}";
        $transaction = $this->createRecruitmentTransaction($recruiter, 'recruitment_offer_escrow', $montant, 'escrow_recruitment_offer_'.$offer->id, $provider, $phone, [
            'recruitment_offer_id' => $offer->id,
            'description' => $label,
        ]);

        return $this->startCheckout(
            $transaction,
            $provider,
            $transaction->client_phone,
            $label,
            ['recruitment_offer_id' => $offer->id],
            $this->bankReference(),
            $label,
            'Paiement',
            [],
        );
    }

    /**
     * Interroge l'opérateur pour une transaction non finalisée et répercute
     * son statut (confirmation → financement du jalon le cas échéant).
     */
    public function refreshStatus(Transaction $transaction): Transaction
    {
        if ($transaction->statut->isFinalized()) {
            // Déjà confirmée par le webhook : s'assurer que le séquestre a suivi.
            $this->applyConfirmedPayment($transaction);

            return $transaction;
        }

        if ($transaction->isWave()) {
            $result = $this->waveService->checkPaymentStatus($transaction->wave_checkout_id);

            if (in_array($result['status'], ['completed', 'success'], true)) {
                $transaction->update([
                    'statut' => PaymentStatus::CONFIRME,
                    'wave_payment_id' => $result['payment_id'],
                    'paid_at' => now(),
                ]);
                $this->applyConfirmedPayment($transaction);
            } elseif (in_array($result['status'], ['failed', 'cancelled'], true)) {
                $transaction->update([
                    'statut' => PaymentStatus::ECHOUE,
                    'failed_at' => now(),
                    'error_message' => 'Paiement Wave échoué ou annulé.',
                ]);
            }
        } elseif ($transaction->isOrangeMoney()) {
            $result = $this->orangeMoneyService->checkPaymentStatus(
                $transaction->orange_order_id,
                $transaction->orange_payment_token
            );

            if (in_array($result['status'], ['SUCCESS', 'SUCCESSFUL'], true)) {
                $transaction->update([
                    'statut' => PaymentStatus::CONFIRME,
                    'orange_tx_reference' => $result['tx_reference'],
                    'paid_at' => now(),
                ]);
                $this->applyConfirmedPayment($transaction);
            } elseif (in_array($result['status'], ['FAILED', 'CANCELLED'], true)) {
                $transaction->update([
                    'statut' => PaymentStatus::ECHOUE,
                    'failed_at' => now(),
                    'error_message' => 'Paiement Orange Money échoué ou annulé.',
                ]);
            }
        }

        return $transaction->refresh();
    }

    public function serializeStatus(Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'status' => $transaction->statut->value,
            'montant' => $transaction->montant,
            'provider' => $transaction->provider->value,
            'mission_id' => $transaction->mission_id,
            'devis_id' => $transaction->metadata['devis_id'] ?? null,
            'order_id' => $transaction->metadata['order_id'] ?? null,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
            'failed_at' => $transaction->failed_at?->toIso8601String(),
        ];
    }

    /**
     * Confirmation par le simulateur de paiement (local/testing uniquement —
     * le contrôle d'environnement reste dans le contrôleur).
     */
    public function confirmSimulatedPayment(Transaction $transaction): void
    {
        $transaction->update([
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
        ]);

        $this->applyConfirmedPayment($transaction);

        $devisId = $transaction->metadata['devis_id'] ?? null;
        if ($devisId) {
            $devis = Devis::find($devisId);
            if ($devis && $devis->statut === 'soumis') {
                $this->devisService->accept($devis, $transaction);
                Log::info("[Paiement validé] Devis #{$devis->id} accepté et séquestre fragmenté pour la mission #{$devis->mission_id}");
            }
        }

        Log::info("Paiement simulé validé pour la transaction #{$transaction->id}");
    }

    public function failSimulatedPayment(Transaction $transaction): void
    {
        $transaction->update([
            'statut' => PaymentStatus::ECHOUE,
            'failed_at' => now(),
            'error_message' => 'Annulé par l\'utilisateur sur le simulateur.',
        ]);

        Log::info("Paiement simulé échoué pour la transaction #{$transaction->id}");
    }

    /**
     * Répercute un paiement confirmé sur le séquestre. Point d'entrée commun
     * aux trois voies de confirmation — webhook opérateur, interrogation de
     * statut par l'app, simulateur local — et idempotent : un webhook arrivé
     * avant l'interrogation laissait sinon le jalon payé mais jamais financé.
     */
    public function applyConfirmedPayment(Transaction $transaction): void
    {
        if (! $transaction->statut->isSuccessful()) {
            return;
        }

        // Séquestres de recrutement (engagement journalier, accès aux candidatures).
        app(RecruitmentEngagementService::class)->applyConfirmedPayment($transaction);

        // Course de livraison réglée par le client : crédit du livreur.
        if (($transaction->metadata['payment_type'] ?? '') === 'delivery_fare') {
            $order = Order::find($transaction->metadata['order_id'] ?? null);
            if ($order) {
                app(OrderService::class)->settleDeliveryFare($order, $transaction);
            }

            return;
        }

        if (($transaction->metadata['payment_type'] ?? '') !== 'jalon') {
            return;
        }

        $jalonId = $transaction->metadata['jalon_id'] ?? null;
        $jalon = $jalonId ? Jalon::find($jalonId) : null;

        if ($jalon) {
            $this->walletService->fundHybridJalon($jalon, $transaction);
        }
    }

    /**
     * Refus métier communs à toute initiation de paiement, vérifiés avant de
     * créer la transaction :
     * - grands comptes : au-delà du seuil Référent, virement bancaire obligatoire ;
     * - virement : indisponible tant que les coordonnées bancaires ne sont pas
     *   renseignées dans le backoffice (jamais de compte inventé, Règle d'or 29).
     */
    private function assertPaymentAllowed(int $montant, PaymentProvider $provider): void
    {
        if ($provider === PaymentProvider::VIREMENT_BANCAIRE && $this->bankTransfer->details() === null) {
            throw new PaymentException('Le paiement par virement bancaire est momentanément indisponible. Veuillez réessayer plus tard ou contacter le support ProsArtisan.', 422);
        }

        $seuil = config('prosartisan.mission.referent_threshold', 2000000);

        if ($montant >= $seuil && $provider !== PaymentProvider::VIREMENT_BANCAIRE) {
            throw new PaymentException('Les paiements Mobile Money sont limités à '.number_format($seuil, 0, ',', ' ').' FCFA. Veuillez effectuer un virement bancaire.', 422);
        }
    }

    /**
     * Un code à propriétaire (`owner_user_id`) ne peut être appliqué que par ce
     * propriétaire — jamais par un autre utilisateur du bon rôle (Règle d'or 36 :
     * propriété de la ressource, pas seulement le rôle).
     *
     * @return array{0: int, 1: ?PromoCode}
     */
    private function resolvePromoDiscount(User $client, ?string $promoCode, int $montant): array
    {
        if (empty($promoCode)) {
            return [0, null];
        }

        $codeStr = strtoupper(trim($promoCode));
        $promo = PromoCode::where('code', $codeStr)->first();

        if ($promo && (! $promo->owner_user_id || $promo->owner_user_id === $client->id)) {
            try {
                return [$promo->calculateDiscount($montant), $promo];
            } catch (\Exception $e) {
                Log::info('Code promo non appliqué à l\'acompte devis: '.$e->getMessage());
            }
        } elseif ($promo) {
            Log::warning("Tentative d'utilisation d'un code promo appartenant à un autre utilisateur", [
                'promo_code' => $codeStr,
                'user_id' => $client->id,
                'owner_user_id' => $promo->owner_user_id,
            ]);
        }

        return [0, null];
    }

    private function findPendingTransaction(Mission $mission, User $client, int $montant, PaymentProvider $provider, array $metadataMatch): ?Transaction
    {
        $query = Transaction::where('mission_id', $mission->id)
            ->where('user_id', $client->id)
            ->where('type', 'acompte')
            ->where('montant', $montant)
            ->where('provider', $provider)
            ->where('statut', PaymentStatus::EN_ATTENTE);

        foreach ($metadataMatch as $key => $value) {
            $query->whereJsonContains("metadata->{$key}", $value);
        }

        return $query->orderBy('id', 'desc')->first();
    }

    /**
     * Réutilise une transaction en attente identique (double tap, retour
     * arrière) au lieu d'ouvrir une seconde session opérateur.
     */
    private function reusePendingTransaction(Transaction $existing, PaymentProvider $provider, string $prefix, array $subject): ?array
    {
        $base = ['transaction_id' => $existing->id] + $subject;

        if ($provider === PaymentProvider::WAVE) {
            $checkoutUrl = $existing->metadata['payment_url'] ?? null;
            if (! $checkoutUrl) {
                return null;
            }

            return [
                'message' => "{$prefix} Wave existant récupéré",
                'data' => $base + [
                    'payment_url' => $checkoutUrl,
                    'wave_launch_url' => $existing->metadata['wave_launch_url'] ?? null,
                    'provider' => 'wave',
                ],
            ];
        }

        if ($provider === PaymentProvider::ORANGE_MONEY) {
            $paymentUrl = $existing->metadata['payment_url'] ?? null;
            $orderId = $existing->orange_order_id ?? null;
            if (! $paymentUrl || ! $orderId) {
                return null;
            }

            return [
                'message' => "{$prefix} Orange Money existant récupéré",
                'data' => $base + [
                    'payment_url' => $paymentUrl,
                    'order_id' => $orderId,
                    'provider' => 'orange_money',
                ],
            ];
        }

        $bank = $this->bankTransfer->details();

        return [
            'message' => "{$prefix} par Virement Bancaire existant récupéré",
            'data' => $base + [
                'provider' => 'virement_bancaire',
                'virement_instructions' => [
                    'bank_name' => $existing->metadata['bank_name'] ?? $bank['bank_name'] ?? null,
                    'account_name' => $existing->metadata['bank_account_name'] ?? $bank['account_name'] ?? null,
                    'iban' => $existing->metadata['bank_iban'] ?? $bank['iban'] ?? null,
                    'reference' => $existing->reference_externe,
                ],
            ],
        ];
    }

    private function createPendingTransaction(Mission $mission, User $client, int $montant, PaymentProvider $provider, string $phone, array $metadata): Transaction
    {
        return Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'acompte',
            'montant' => $montant,
            'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_'.$client->id : 'client_mobile_money_'.$client->id,
            'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => $provider,
            'statut' => PaymentStatus::EN_ATTENTE,
            'client_phone' => $phone,
            'metadata' => $metadata,
        ]);
    }

    private function createRecruitmentTransaction(User $recruiter, string $type, int $montant, string $walletDest, PaymentProvider $provider, ?string $phone, array $metadata): Transaction
    {
        return Transaction::create([
            'user_id' => $recruiter->id,
            'type' => $type,
            'montant' => $montant,
            'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_'.$recruiter->id : 'client_mobile_money_'.$recruiter->id,
            'wallet_dest' => $walletDest,
            'provider' => $provider,
            'statut' => PaymentStatus::EN_ATTENTE,
            'client_phone' => (string) ($phone ?? $recruiter->phone ?? ''),
            'metadata' => $metadata,
        ]);
    }

    private function bankReference(): string
    {
        return 'REF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Ouvre la session chez l'opérateur (ou émet les instructions de virement)
     * et enregistre ses références sur la transaction.
     */
    private function startCheckout(
        Transaction $transaction,
        PaymentProvider $provider,
        string $phone,
        string $label,
        array $operatorMetadata,
        string $bankReference,
        string $bankDescription,
        string $prefix,
        array $subject,
    ): array {
        $base = ['transaction_id' => $transaction->id] + $subject;
        $operatorMetadata = ['transaction_id' => $transaction->id] + $operatorMetadata;

        if ($provider === PaymentProvider::WAVE) {
            $result = $this->waveService->createCheckout($transaction->montant, $phone, $label, $operatorMetadata);

            $transaction->update([
                'wave_checkout_id' => $result['checkout_id'],
                'wave_client_reference' => $result['checkout_id'],
                'reference_externe' => $result['checkout_id'],
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'payment_url' => $result['checkout_url'],
                    'wave_launch_url' => $result['wave_launch_url'],
                ]),
            ]);

            return [
                'message' => "{$prefix} Wave initié avec succès",
                'data' => $base + [
                    'payment_url' => $result['checkout_url'],
                    'wave_launch_url' => $result['wave_launch_url'],
                    'provider' => 'wave',
                ],
            ];
        }

        if ($provider === PaymentProvider::ORANGE_MONEY) {
            $result = $this->orangeMoneyService->createPayment($transaction->montant, $phone, $label, $operatorMetadata);

            $transaction->update([
                'orange_order_id' => $result['order_id'],
                'orange_payment_token' => $result['payment_token'],
                'reference_externe' => $result['order_id'],
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'payment_url' => $result['payment_url'],
                    'order_id' => $result['order_id'],
                ]),
            ]);

            return [
                'message' => "{$prefix} Orange Money initié avec succès",
                'data' => $base + [
                    'payment_url' => $result['payment_url'],
                    'order_id' => $result['order_id'],
                    'provider' => 'orange_money',
                ],
            ];
        }

        // Vérifiées disponibles par assertPaymentAllowed() avant la création de la transaction.
        $bank = $this->bankTransfer->details();

        $transaction->update([
            'reference_externe' => $bankReference,
            'metadata' => array_merge($transaction->metadata ?? [], [
                'bank_name' => $bank['bank_name'],
                'bank_account_name' => $bank['account_name'],
                'bank_iban' => $bank['iban'],
                'bank_reference' => $bankReference,
                'description' => $bankDescription,
            ]),
        ]);

        return [
            'message' => "{$prefix} par Virement Bancaire initié avec succès",
            'data' => $base + [
                'provider' => 'virement_bancaire',
                'virement_instructions' => [
                    'bank_name' => $bank['bank_name'],
                    'account_name' => $bank['account_name'],
                    'iban' => $bank['iban'],
                    'reference' => $bankReference,
                ],
            ],
        ];
    }
}
