<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bordereau de Décaissement Cash-Out - {{ $cashout->reference }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.4;
            background-color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-bottom: 3px solid #ebb95e;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 900;
            color: #241b16;
            letter-spacing: -0.5px;
        }
        .logo-sub {
            font-size: 10px;
            color: #d97706;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-title {
            text-align: right;
        }
        .doc-title h1 {
            margin: 0;
            font-size: 18px;
            color: #111827;
            font-weight: 800;
            text-transform: uppercase;
        }
        .doc-title .ref {
            font-size: 11px;
            color: #6b7280;
            margin-top: 3px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 12px;
            background-color: #fef3c7;
            color: #92400e;
            text-transform: uppercase;
        }
        .grid-table {
            width: 100%;
            margin-bottom: 18px;
        }
        .grid-table td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .card-label {
            font-size: 9px;
            font-weight: 800;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .info-row {
            margin-bottom: 4px;
        }
        .info-label {
            color: #6b7280;
            font-weight: 600;
            display: inline-block;
            width: 110px;
        }
        .info-value {
            color: #111827;
            font-weight: bold;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .details-table th {
            background-color: #241b16;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
        }
        .details-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .details-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .amount-box {
            background-color: #fffbeb;
            border: 2px solid #ebb95e;
            border-radius: 8px;
            padding: 14px 18px;
            text-align: right;
            margin-bottom: 25px;
        }
        .amount-label {
            font-size: 11px;
            color: #92400e;
            font-weight: 700;
            text-transform: uppercase;
        }
        .amount-val {
            font-size: 22px;
            font-weight: 900;
            color: #b45309;
            margin-top: 2px;
        }
        .stamp-box {
            width: 100%;
            margin-top: 20px;
            border-top: 1px dashed #d1d5db;
            padding-top: 15px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="logo-text">ProsArtisan<span style="color: #ebb95e;">.ci</span></div>
                <div class="logo-sub">Module Trésorerie & Cash-Out Partenaires</div>
            </td>
            <td class="doc-title" style="vertical-align: middle;">
                <h1>BORDEREAU DE CASHOUT QUINCAILLERIE</h1>
                <div class="ref">RÉF : <strong>{{ $cashout->reference }}</strong></div>
                <div style="margin-top: 5px;">
                    <span class="badge">{{ strtoupper($cashout->statut) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="grid-table" cellspacing="10" cellpadding="0">
        <tr>
            <td>
                <div class="card-label">Quincaillerie / Bénéficiaire</div>
                <div class="info-row">
                    <span class="info-label">Boutique :</span>
                    <span class="info-value">{{ $cashout->supplier->name ?? 'Quincaillerie Partenaire' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact / Tél :</span>
                    <span class="info-value">{{ $cashout->beneficiary_phone }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nom du récepteur :</span>
                    <span class="info-value">{{ $cashout->beneficiary_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID Fournisseur :</span>
                    <span class="info-value">#{{ $cashout->supplier_id }}</span>
                </div>
            </td>
            <td>
                <div class="card-label">Modalités de Règlement</div>
                <div class="info-row">
                    <span class="info-label">Date de demande :</span>
                    <span class="info-value">{{ $cashout->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date d'exécution :</span>
                    <span class="info-value">{{ $cashout->processed_at ? $cashout->processed_at->format('d/m/Y H:i') : 'En attente' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Canal de versement :</span>
                    <span class="info-value">{{ strtoupper($cashout->provider) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Validé par Admin :</span>
                    <span class="info-value">{{ $cashout->processor->name ?? 'Administration' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 55%;">Détail du décaissement</th>
                <th style="width: 20%; text-align: center;">Taux Appliqué</th>
                <th style="width: 25%; text-align: right;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Montant brut prélevé sur le portefeuille matériaux</td>
                <td style="text-align: center;">100 %</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($cashout->montant_brut, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Commission de gestion & retrait ProsArtisan</td>
                <td style="text-align: center; color: #dc2626;">
                    {{ $cashout->montant_brut > 0 ? round(($cashout->montant_commission / $cashout->montant_brut) * 100, 1) : 0 }} %
                </td>
                <td style="text-align: right; color: #dc2626; font-weight: bold;">- {{ number_format($cashout->montant_commission, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr style="background-color: #fefce8; font-weight: bold;">
                <td>Net à transférer sur compte Mobile Money</td>
                <td style="text-align: center; color: #b45309;">Net décaissé</td>
                <td style="text-align: right; color: #b45309; font-size: 12px;">{{ number_format($cashout->montant_net, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="amount-box">
        <div class="amount-label">Montant Total Net Viré au Partenaire</div>
        <div class="amount-val">{{ number_format($cashout->montant_net, 0, ',', ' ') }} FCFA</div>
    </div>

    <table class="stamp-box" style="border: none;">
        <tr>
            <td style="width: 65%; border: none; vertical-align: middle;">
                <div style="border: 1px solid #ebb95e; background: #fffdf5; padding: 8px 12px; border-radius: 6px; font-size: 9px; color: #78350f;">
                    💼 <strong>RÈGLEMENT DE TRÉSORERIE FOURNISSEUR AGRÉÉ</strong><br>
                    Ce document fait office de reçu libératoire et d'attestation comptable pour le retrait de fonds
                    matériaux validé sur la plateforme ProsArtisan Côte d'Ivoire.
                </div>
            </td>
            <td style="width: 35%; border: none; text-align: right; vertical-align: middle;">
                <div style="font-size: 9px; color: #4b5563; font-weight: bold;">Direction Financière ProsArtisan</div>
                <div style="font-size: 8px; color: #b45309; margin-top: 2px;">Bordereau Exécuté</div>
                <div style="font-size: 7px; color: #9ca3af; font-family: monospace; margin-top: 3px;">SIG: {{ substr(hash('sha256', $cashout->reference . $cashout->montant_net), 0, 24) }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        ProsArtisan Côte d'Ivoire • Abidjan Plateau • Service Trésorerie Partenaires • www.prosartisan.ci
    </div>
</body>
</html>
