<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\Litige;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeneratedDocumentService
{
    public function __construct(
        private PdfService $pdfService
    ) {}

    /**
     * Liste paginée des documents avec filtres.
     */
    public function listDocuments(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->ensureSync();

        $query = GeneratedDocument::with([
            'user:id,name,phone,role',
            'mission:id,status,montant_total',
            'transaction',
            'supplierCashout',
            'litige',
        ])->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * KPIs & Statistiques consolidées des documents et fonds libérés.
     */
    public function getStats(): array
    {
        $this->ensureSync();

        $totalDocuments = GeneratedDocument::count();
        $totalMontantDecaisse = (int) GeneratedDocument::whereIn('document_type', [
            'recu_liberation_jalon',
            'recu_paiement_fournisseur',
            'recu_cashout',
            'facture_litige',
        ])->sum('montant');

        $countJalonsMo = GeneratedDocument::where('document_type', 'recu_liberation_jalon')->count();
        $countFournisseurs = GeneratedDocument::whereIn('document_type', ['recu_paiement_fournisseur', 'recu_cashout'])->count();
        $countRapportsLitiges = GeneratedDocument::whereIn('document_type', ['facture_litige', 'rapport_solvabilite'])->count();

        return [
            'total_documents'          => $totalDocuments,
            'total_montant_certifie'   => $totalMontantDecaisse,
            'recus_jalons_mo'          => $countJalonsMo,
            'recus_quincaillerie'      => $countFournisseurs,
            'rapports_et_litiges'      => $countRapportsLitiges,
        ];
    }

    /**
     * Crée ou récupère le document associé à une transaction de libération / paiement.
     */
    public function createOrGetForTransaction(Transaction $tx): GeneratedDocument
    {
        $existing = GeneratedDocument::where('transaction_id', $tx->id)->first();
        if ($existing) {
            return $existing;
        }

        $type = match ($tx->type) {
            'liberation_jalon' => 'recu_liberation_jalon',
            'paiement_fournisseur' => 'recu_paiement_fournisseur',
            'remboursement' => 'recu_remboursement',
            'credit' => 'recu_avance_credit',
            'acompte' => 'recu_acompte',
            default => 'recu_paiement',
        };

        $title = match ($tx->type) {
            'liberation_jalon' => "Reçu de Libération Jalon Main-d'Œuvre #{$tx->mission_id}",
            'paiement_fournisseur' => "Reçu de Règlement Matériaux J-Code #{$tx->mission_id}",
            'remboursement' => "Reçu de Remboursement Séquestre #{$tx->mission_id}",
            'credit' => "Attestation d'Avance Micro-Crédit",
            'acompte' => "Reçu d'Acompte Séquestre Mission #{$tx->mission_id}",
            default => "Pièce Justificative de Transaction #{$tx->id}",
        };

        $ref = 'REC-' . strtoupper(substr($tx->provider instanceof \BackedEnum ? $tx->provider->value : (string)$tx->provider, 0, 2)) . '-' . str_pad($tx->id, 6, '0', STR_PAD_LEFT);

        return GeneratedDocument::create([
            'reference'      => $ref,
            'document_type'  => $type,
            'title'          => $title,
            'user_id'        => $tx->user_id ?? $tx->mission?->artisan_id ?? $tx->mission?->client_id,
            'mission_id'     => $tx->mission_id,
            'transaction_id' => $tx->id,
            'montant'        => (int) $tx->montant,
            'mime_type'      => 'application/pdf',
            'metadata'       => [
                'provider'           => $tx->provider instanceof \BackedEnum ? $tx->provider->value : (string)$tx->provider,
                'reference_externe'  => $tx->reference_externe,
                'statut'             => $tx->statut instanceof \BackedEnum ? $tx->statut->value : (string)$tx->statut,
            ],
            'created_at'     => $tx->paid_at ?? $tx->created_at,
        ]);
    }

    /**
     * Crée ou récupère le document associé à un cash-out quincaillerie.
     */
    public function createOrGetForCashout(SupplierCashout $cashout): GeneratedDocument
    {
        $existing = GeneratedDocument::where('supplier_cashout_id', $cashout->id)->first();
        if ($existing) {
            return $existing;
        }

        $ref = 'CSH-' . str_pad($cashout->id, 6, '0', STR_PAD_LEFT);

        return GeneratedDocument::create([
            'reference'            => $ref,
            'document_type'        => 'recu_cashout',
            'title'                => "Bordereau de Cash-Out Quincaillerie ({$cashout->reference})",
            'user_id'              => $cashout->supplier_id,
            'supplier_cashout_id'  => $cashout->id,
            'montant'              => (int) $cashout->montant_net,
            'mime_type'            => 'application/pdf',
            'metadata'             => [
                'montant_brut'        => (int) $cashout->montant_brut,
                'montant_commission'  => (int) $cashout->montant_commission,
                'beneficiary_phone'   => $cashout->beneficiary_phone,
                'beneficiary_name'    => $cashout->beneficiary_name,
                'provider'            => $cashout->provider,
                'statut'              => $cashout->statut,
            ],
            'created_at'           => $cashout->processed_at ?? $cashout->created_at,
        ]);
    }

    /**
     * Crée ou enregistre le document lié à un arbitrage de litige.
     */
    public function createOrGetForLitige(Litige $litige, ?string $path = null): GeneratedDocument
    {
        $existing = GeneratedDocument::where('litige_id', $litige->id)->first();
        if ($existing) {
            return $existing;
        }

        $ref = 'FAC-LIT-' . str_pad($litige->id, 6, '0', STR_PAD_LEFT);
        $mission = $litige->mission;

        return GeneratedDocument::create([
            'reference'      => $ref,
            'document_type'  => 'facture_litige',
            'title'          => "Facture de Décaissement Litige #{$litige->id} (Mission #{$litige->mission_id})",
            'user_id'        => $litige->declencheur_id,
            'mission_id'     => $litige->mission_id,
            'litige_id'      => $litige->id,
            'montant'        => (int) ($mission?->montant_total ?? 0),
            'file_path'      => $path,
            'mime_type'      => 'application/pdf',
            'metadata'       => [
                'decision'     => $litige->decision,
                'statut'       => $litige->statut,
            ],
            'created_at'     => $litige->resolu_at ?? $litige->updated_at ?? now(),
        ]);
    }

    /**
     * Enregistre un rapport de solvabilité pour un artisan.
     */
    public function recordSolvabilityReport(User $artisan, string $path): GeneratedDocument
    {
        $ref = 'SOLV-' . str_pad($artisan->id, 5, '0', STR_PAD_LEFT) . '-' . now()->format('ymdHi');

        return GeneratedDocument::create([
            'reference'      => $ref,
            'document_type'  => 'rapport_solvabilite',
            'title'          => "Rapport d'Éligibilité & Solvabilité Bancaire - {$artisan->name}",
            'user_id'        => $artisan->id,
            'montant'        => 0,
            'file_path'      => $path,
            'mime_type'      => 'application/pdf',
            'metadata'       => [
                'score_prosartisan' => $artisan->score_prosartisan,
                'phone'             => $artisan->phone,
            ],
            'created_at'     => now(),
        ]);
    }

    /**
     * Récupère ou génère à la volée le fichier PDF d'un document.
     */
    public function getPdfPath(GeneratedDocument $doc): string
    {
        // Si un fichier existe déjà et est valide
        if ($doc->file_path && file_exists($doc->file_path)) {
            return $doc->file_path;
        }

        $generatedPath = null;

        if ($doc->transaction_id && $doc->transaction) {
            $generatedPath = $this->pdfService->generatePaymentReceipt($doc->transaction);
        } elseif ($doc->supplier_cashout_id && $doc->supplierCashout) {
            $generatedPath = $this->pdfService->generateCashoutReceipt($doc->supplierCashout);
        } elseif ($doc->litige_id && $doc->litige && $doc->mission) {
            $amount = (int) ($doc->montant > 0 ? $doc->montant : $doc->mission->montant_total);
            $generatedPath = $this->pdfService->generateDisbursementInvoice($doc->mission, $amount);
        } elseif ($doc->document_type === 'rapport_solvabilite' && $doc->user) {
            $generatedPath = $this->pdfService->generateSolvabilityReport($doc->user);
        }

        if ($generatedPath) {
            $doc->update([
                'file_path' => $generatedPath,
                'file_size' => file_exists($generatedPath) ? filesize($generatedPath) : null,
            ]);
            return $generatedPath;
        }

        throw new \RuntimeException("Impossible de générer le document PDF pour la référence {$doc->reference}.");
    }

    /**
     * Synchronise les transactions et cash-outs confirmés dans la table generated_documents.
     */
    public function syncAll(): int
    {
        if (! Schema::hasTable('generated_documents')) {
            return 0;
        }

        $count = 0;

        // 1. Transactions confirmées de type libération ou décaissement
        if (Schema::hasTable('transactions')) {
            $transactions = Transaction::whereIn('type', [
                'liberation_jalon',
                'paiement_fournisseur',
                'acompte',
                'remboursement',
                'credit',
            ])->where('statut', 'confirme')
              ->whereNotIn('id', function ($query) {
                  $query->select('transaction_id')
                      ->from('generated_documents')
                      ->whereNotNull('transaction_id');
              })->get();

            foreach ($transactions as $tx) {
                $this->createOrGetForTransaction($tx);
                $count++;
            }
        }

        // 2. Cashouts quincailleries
        if (Schema::hasTable('supplier_cashouts')) {
            $cashouts = SupplierCashout::whereNotIn('id', function ($query) {
                $query->select('supplier_cashout_id')
                    ->from('generated_documents')
                    ->whereNotNull('supplier_cashout_id');
            })->get();

            foreach ($cashouts as $cashout) {
                $this->createOrGetForCashout($cashout);
                $count++;
            }
        }

        // 3. Litiges résolus avec facture
        if (Schema::hasTable('litiges')) {
            $litiges = Litige::whereNotNull('resolution_payload')
                ->whereNotIn('id', function ($query) {
                    $query->select('litige_id')
                        ->from('generated_documents')
                        ->whereNotNull('litige_id');
                })->get();

            foreach ($litiges as $litige) {
                $path = $litige->resolution_payload['invoice_path'] ?? null;
                $this->createOrGetForLitige($litige, $path);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Auto-sync léger pour s'assurer qu'au moins les récentes pièces sont enregistrées.
     */
    private function ensureSync(): void
    {
        static $synced = false;
        if (! $synced && Schema::hasTable('generated_documents')) {
            $this->syncAll();
            $synced = true;
        }
    }
}
