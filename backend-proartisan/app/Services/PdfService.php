<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function __construct(private ScoreService $scoreService) {}

    /**
     * Génère le rapport PDF de solvabilité pour microfinances.
     */
    public function generateSolvabilityReport(User $artisan): string
    {
        $scoreDetail = $this->scoreService->getScoreDetail($artisan);

        // Données du rapport
        $data = [
            'artisan' => $artisan,
            'score_detail' => $scoreDetail,
            'missions_completed' => $artisan->missionsArtisan()->where('status', 'completed')->count(),
            'total_earnings' => $artisan->missionsArtisan()->where('status', 'completed')->sum('montant_mo'),
            'generated_at' => now()->format('d/m/Y H:i'),
        ];

        // Générer PDF
        $pdf = Pdf::loadView('pdf.solvability_report', $data);

        // Stocker sur disque temporairement
        $filename = "solvability_report_{$artisan->id}_" . now()->format('YmdHis') . ".pdf";
        $path = storage_path("app/public/reports/{$filename}");
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Génère la facture de décaissement pour une mission suite à un arbitrage.
     */
    public function generateDisbursementInvoice(\App\Models\Mission $mission, int $amountReleased): string
    {
        $data = [
            'mission'         => $mission,
            'client'          => $mission->client,
            'artisan'         => $mission->artisan,
            'amount_released' => $amountReleased,
            'generated_at'    => now()->format('d/m/Y H:i'),
            'invoice_number'  => 'FAC-' . str_pad($mission->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('Ymd'),
        ];

        // Générer PDF
        $pdf = Pdf::loadView('pdf.disbursement_invoice', $data);

        // Stocker sur disque
        $filename = "disbursement_invoice_{$mission->id}_" . now()->format('YmdHis') . ".pdf";
        $path = storage_path("app/public/invoices/{$filename}");
        
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Génère le reçu officiel de paiement / libération de fonds pour une transaction.
     */
    public function generatePaymentReceipt(\App\Models\Transaction $transaction): string
    {
        $transaction->loadMissing(['mission.artisan', 'mission.client', 'user']);

        $mission = $transaction->mission;
        $user = $transaction->user ?? $mission?->artisan ?? $mission?->client;

        // Déterminer le libellé du type de libération
        $disbursementType = match ($transaction->type) {
            'liberation_jalon' => 'Libération Jalon Main d\'Œuvre',
            'paiement_fournisseur' => 'Règlement Matériaux J-Code',
            'remboursement' => 'Remboursement Séquestre',
            'credit' => 'Avance Micro-Crédit',
            'acompte' => 'Acompte Séquestre Mission',
            default => 'Décaissement Plateforme',
        };

        $badgeLabel = match ($transaction->type) {
            'liberation_jalon' => 'Fonds Libérés (Artisan)',
            'paiement_fournisseur' => 'Fonds Libérés (Fournisseur)',
            'remboursement' => 'Remboursement Validé',
            'credit' => 'Financement Accordé',
            default => 'Transaction Confirmée',
        };

        $receiptNumber = 'REC-TX-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);

        $description = match ($transaction->type) {
            'liberation_jalon' => "Versement des honoraires de main-d'œuvre pour le jalon validé sur la mission #{$transaction->mission_id}.",
            'paiement_fournisseur' => "Paiement des fournitures et matériaux du bon J-Code validé sur la mission #{$transaction->mission_id}.",
            'remboursement' => "Remboursement des fonds placés sous séquestre pour la mission #{$transaction->mission_id}.",
            'credit' => "Octroi de micro-crédit d'urgence matériel pour l'artisan.",
            default => "Libération de fonds pour l'opération #{$transaction->id}.",
        };

        $data = [
            'transaction'        => $transaction,
            'receipt_number'     => $receiptNumber,
            'badge_label'        => $badgeLabel,
            'disbursement_type'  => $disbursementType,
            'description'        => $description,
            'sub_details'        => $mission ? "Catégorie : " . ($mission->category ?? 'Artisanat') . " • Statut : {$mission->status}" : null,
            'beneficiary_name'   => $user?->name ?? 'Bénéficiaire ProsArtisan',
            'beneficiary_phone'  => $user?->phone ?? $transaction->client_phone ?? 'N/A',
            'beneficiary_role'   => $user?->role ?? 'artisan',
            'beneficiary_id'     => $user?->id,
            'payment_date'       => ($transaction->paid_at ?? $transaction->created_at)->format('d/m/Y H:i'),
            'mission_id'         => $transaction->mission_id,
            'provider'           => $transaction->provider instanceof \BackedEnum ? $transaction->provider->value : (string)$transaction->provider,
            'external_reference' => $transaction->reference_externe ?? $transaction->wave_payment_id ?? $transaction->orange_tx_reference,
            'transaction_id'     => $transaction->id,
            'amount'             => (int) $transaction->montant,
            'deductions'         => 0,
        ];

        $pdf = Pdf::loadView('pdf.payment_receipt', $data);

        $filename = "recu_paiement_{$transaction->id}_" . now()->format('YmdHis') . ".pdf";
        $path = storage_path("app/public/receipts/{$filename}");

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Génère le bordereau officiel de cash-out pour une quincaillerie.
     */
    public function generateCashoutReceipt(\App\Models\SupplierCashout $cashout): string
    {
        $cashout->loadMissing(['supplier', 'processor']);

        $pdf = Pdf::loadView('pdf.cashout_receipt', [
            'cashout' => $cashout,
        ]);

        $filename = "cashout_receipt_{$cashout->id}_" . now()->format('YmdHis') . ".pdf";
        $path = storage_path("app/public/cashouts/{$filename}");

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}
