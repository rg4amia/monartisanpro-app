<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu de Paiement - {{ $receipt_number }}</title>
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
            border-bottom: 3px solid #10b981;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-text {
            font-size: 24px;
            font-weight: 900;
            color: #065f46;
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
            background-color: #d1fae5;
            color: #065f46;
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
            width: 90px;
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
            background-color: #065f46;
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
            background-color: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 14px 18px;
            text-align: right;
            margin-bottom: 25px;
        }
        .amount-label {
            font-size: 11px;
            color: #065f46;
            font-weight: 700;
            text-transform: uppercase;
        }
        .amount-val {
            font-size: 22px;
            font-weight: 900;
            color: #047857;
            margin-top: 2px;
        }
        .amount-words {
            font-size: 10px;
            color: #4b5563;
            font-style: italic;
            margin-top: 4px;
        }
        .stamp-box {
            width: 100%;
            margin-top: 20px;
            border-top: 1px dashed #d1d5db;
            padding-top: 15px;
        }
        .security-badge {
            border: 1px solid #10b981;
            background: #f0fdf4;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            font-size: 9px;
            color: #065f46;
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
                <div class="logo-sub">Plateforme Nationale de Confiance & Séquestre Garanti</div>
            </td>
            <td class="doc-title" style="vertical-align: middle;">
                <h1>REÇU DE PAIEMENT & DÉCAISSEMENT</h1>
                <div class="ref">RÉF : <strong>{{ $receipt_number }}</strong></div>
                <div style="margin-top: 5px;">
                    <span class="badge">{{ $badge_label ?? 'Fonds Libérés' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="grid-table" cellspacing="10" cellpadding="0">
        <tr>
            <td>
                <div class="card-label">Bénéficiaire des Fonds</div>
                <div class="info-row">
                    <span class="info-label">Nom complet :</span>
                    <span class="info-value">{{ $beneficiary_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Téléphone :</span>
                    <span class="info-value">{{ $beneficiary_phone }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Rôle :</span>
                    <span class="info-value">{{ strtoupper($beneficiary_role ?? 'Artisan') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID Utilisateur :</span>
                    <span class="info-value">#{{ $beneficiary_id ?? 'N/A' }}</span>
                </div>
            </td>
            <td>
                <div class="card-label">Détails Opérationnels & Traçabilité</div>
                <div class="info-row">
                    <span class="info-label">Date & Heure :</span>
                    <span class="info-value">{{ $payment_date }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mission ID :</span>
                    <span class="info-value">#{{ $mission_id ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Opérateur :</span>
                    <span class="info-value">{{ strtoupper($provider ?? 'Mobile Money') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Réf. Externe :</span>
                    <span class="info-value" style="font-family: monospace; font-size: 10px;">{{ $external_reference ?? 'TRANS-' . $transaction_id }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 55%;">Désignation de la transaction</th>
                <th style="width: 20%; text-align: center;">Type de Libération</th>
                <th style="width: 25%; text-align: right;">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $description }}</strong>
                    @if(!empty($sub_details))
                        <br><span style="font-size: 9px; color: #6b7280;">{{ $sub_details }}</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    <span style="font-weight: 600; color: #047857;">{{ $disbursement_type }}</span>
                </td>
                <td style="text-align: right; font-weight: bold;">
                    {{ number_format($amount, 0, ',', ' ') }} FCFA
                </td>
            </tr>
            @if(!empty($deductions))
                <tr>
                    <td>Commission plateforme de médiation / service</td>
                    <td style="text-align: center; color: #6b7280;">Retenue</td>
                    <td style="text-align: right; color: #dc2626;">- {{ number_format($deductions, 0, ',', ' ') }} FCFA</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="amount-box">
        <div class="amount-label">Montant Net Décaissé & Transféré</div>
        <div class="amount-val">{{ number_format($amount, 0, ',', ' ') }} FCFA</div>
        <div class="amount-words">Payé avec succès via {{ strtoupper($provider ?? 'Mobile Money') }}</div>
    </div>

    <table class="stamp-box" style="border: none;">
        <tr>
            <td style="width: 65%; border: none; vertical-align: middle;">
                <div class="security-badge">
                    🔒 <strong>CERTIFICATION D'INTÉGRITÉ PROSARTISAN CI</strong><br>
                    Ce reçu électronique certifie que les fonds ci-dessus ont été formellement débloqués du compte de séquestre
                    et versés au compte Mobile Money du bénéficiaire sous le protocole de vérification OTP/GPS.
                </div>
            </td>
            <td style="width: 35%; border: none; text-align: right; vertical-align: middle;">
                <div style="font-size: 9px; color: #4b5563; font-weight: bold;">Cachet Électronique ProsArtisan</div>
                <div style="font-size: 8px; color: #059669; margin-top: 2px;">Vérifié & Signé numériquement</div>
                <div style="font-size: 7px; color: #9ca3af; font-family: monospace; margin-top: 3px;">SHA256: {{ substr(hash('sha256', $receipt_number . $amount . $payment_date), 0, 24) }}...</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        ProsArtisan Côte d'Ivoire SARL • Immeuble ProsArtisan, Plateau, Abidjan • Support : (+225) 07 00 00 00 00 • contact@prosartisan.ci • www.prosartisan.ci
        <br>Document généré automatiquement à des fins de comptabilité et de transparence financière.
    </div>
</body>
</html>
