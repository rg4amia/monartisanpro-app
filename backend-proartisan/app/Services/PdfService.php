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
}
