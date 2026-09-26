<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\GeneratedDocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reçu PDF d'une transaction, servi par URL signée à courte durée de vie
 * (Chantier 11, même modèle que les pièces KYC — Règle d'or 40) : l'app
 * mobile l'ouvre dans le navigateur sans y transmettre son jeton. Le lien
 * n'est délivré qu'au titulaire de la transaction
 * (`GET /api/v1/transactions/{transaction}/receipt-link`).
 */
class ReceiptController extends Controller
{
    public function __construct(private GeneratedDocumentService $documents) {}

    public function show(Transaction $transaction): BinaryFileResponse
    {
        abort_unless($transaction->statut->isSuccessful(), 404);

        $path = $this->documents->getPdfPath($this->documents->createOrGetForTransaction($transaction));

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-prosartisan-'.$transaction->id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900, no-store',
        ]);
    }
}
