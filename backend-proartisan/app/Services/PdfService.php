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
            'missions_completed' => $artisan->missionsArtisan()->where('status', 'terminee')->count(),
            'total_earnings' => $artisan->missionsArtisan()->where('status', 'terminee')->sum('montant_mo'),
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
}
