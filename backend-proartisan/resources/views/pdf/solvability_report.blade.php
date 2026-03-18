<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Solvabilité - {{ $artisan->name }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4F46E5; padding-bottom: 10px; }
        .score-container { text-align: center; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .score { font-size: 48px; font-weight: bold; color: #4F46E5; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f3f4f6; color: #4F46E5; }
        .footer { margin-top: 50px; font-size: 10px; text-align: center; color: #666; }
        .status-eligible { color: green; font-weight: bold; }
        .status-ineligible { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RAPPORT DE SOLVABILITÉ</h1>
        <h2>ProsArtisan - Côte d'Ivoire</h2>
        <p>Généré le {{ $generated_at }}</p>
    </div>

    <h3>Informations de l'Artisan</h3>
    <p><strong>Nom :</strong> {{ $artisan->name }}</p>
    <p><strong>Téléphone :</strong> {{ $artisan->phone }}</p>
    <p><strong>Date d'inscription :</strong> {{ $artisan->created_at->format('d/m/Y') }}</p>

    <div class="score-container">
        <h3>Score N'Zassa Global</h3>
        <div class="score">{{ $score_detail['score_nzassa'] }}/100</div>
        <p>Ce score représente la fiabilité globale calculée par l'algorithme ProsArtisan.</p>
    </div>

    <h3>Détail des Critères</h3>
    <table>
        <tr>
            <th>Critère</th>
            <th>Poids</th>
            <th>Note Moyenne (sur 5)</th>
        </tr>
        <tr>
            <td>Fiabilité</td>
            <td>40%</td>
            <td>{{ $score_detail['breakdown']['fiabilite'] }}</td>
        </tr>
        <tr>
            <td>Intégrité</td>
            <td>30%</td>
            <td>{{ $score_detail['breakdown']['integrite'] }}</td>
        </tr>
        <tr>
            <td>Qualité</td>
            <td>20%</td>
            <td>{{ $score_detail['breakdown']['qualite'] }}</td>
        </tr>
        <tr>
            <td>Réactivité</td>
            <td>10%</td>
            <td>{{ $score_detail['breakdown']['reactivite'] }}</td>
        </tr>
    </table>

    <h3>Historique d'Activité</h3>
    <ul>
        <li><strong>Missions complétées :</strong> {{ $missions_completed }}</li>
        <li><strong>Revenus totaux générés :</strong> {{ number_format($total_earnings, 0, ',', ' ') }} FCFA</li>
        <li><strong>Nombre d'évaluations clients :</strong> {{ $score_detail['total_evaluations'] }}</li>
        <li><strong>Note moyenne clients :</strong> {{ $score_detail['average_rating'] }}/5</li>
    </ul>

    <h3>Éligibilité au Micro-crédit</h3>
    @if($score_detail['micro_credit_eligible'])
        <p class="status-eligible">✓ ÉLIGIBLE (Score ≥ 70)</p>
        <p>L'artisan présente un profil à faible risque, favorable pour un déblocage de fonds rapide.</p>
    @else
        <p class="status-ineligible">✗ NON ÉLIGIBLE (Score < 70)</p>
        <p>Un score de 70 est requis pour l'accès prioritaire au micro-crédit.</p>
    @endif

    <div class="footer">
        <p>Ce document est généré automatiquement par la plateforme ProsArtisan. Il fait foi de l'historique de l'artisan sur la plateforme à la date indiquée.</p>
        <p>© 2026 ProsArtisan Côte d'Ivoire - www.prosartisan.ci</p>
    </div>
</body>
</html>
