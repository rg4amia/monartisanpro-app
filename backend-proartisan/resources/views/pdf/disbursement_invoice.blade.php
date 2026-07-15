<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture de Décaissement - {{ $invoice_number }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #10B981; padding-bottom: 10px; }
        .invoice-details { margin-bottom: 20px; }
        .grid { width: 100%; margin-bottom: 20px; }
        .grid td { width: 50%; vertical-align: top; }
        .section-title { font-size: 14px; font-weight: bold; color: #10B981; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f3f4f6; color: #10B981; }
        .total-box { margin-top: 30px; text-align: right; font-size: 16px; font-weight: bold; color: #10B981; }
        .footer { margin-top: 50px; font-size: 10px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FACTURE DE DÉCAISSEMENT</h1>
        <h2>ProsArtisan - Côte d'Ivoire</h2>
        <p>Générée le {{ $generated_at }}</p>
    </div>

    <div class="invoice-details">
        <p><strong>Numéro de Facture :</strong> {{ $invoice_number }}</p>
        <p><strong>Mission ID :</strong> #{{ $mission->id }}</p>
    </div>

    <table class="grid" style="border: none; margin-bottom: 30px;">
        <tr style="border: none;">
            <td style="border: none; padding-right: 20px;">
                <div class="section-title">Client</div>
                <p><strong>Nom :</strong> {{ $client->name ?? 'N/A' }}</p>
                <p><strong>Téléphone :</strong> {{ $client->phone }}</p>
            </td>
            <td style="border: none; padding-left: 20px;">
                <div class="section-title">Artisan (Bénéficiaire)</div>
                <p><strong>Nom :</strong> {{ $artisan->name ?? 'N/A' }}</p>
                <p><strong>Téléphone :</strong> {{ $artisan->phone }}</p>
            </td>
        </tr>
    </table>

    <div class="section-title">Détails de l'arbitrage</div>
    <p>Cette facture atteste de la libération forcée des fonds en séquestre suite à la décision d'arbitrage de l'administration ProsArtisan en faveur de l'artisan.</p>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Fonds de main-d'œuvre débloqués</td>
                <td style="text-align: right;">{{ number_format($mission->montant_mo, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Fonds de matériaux débloqués</td>
                <td style="text-align: right;">{{ number_format($mission->montant_materiaux, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f9fafb;">
                <td>Montant Total Décaissé</td>
                <td style="text-align: right; color: #10B981;">{{ number_format($amount_released, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Ce document est une pièce justificative financière générée automatiquement par la plateforme ProsArtisan suite à un arbitrage de litige.</p>
        <p>© 2026 ProsArtisan Côte d'Ivoire - www.prosartisan.ci</p>
    </div>
</body>
</html>
