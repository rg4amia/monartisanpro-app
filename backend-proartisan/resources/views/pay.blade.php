<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProsArtisan — Simulation de Paiement</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #10b981;
            --success-hover: #059669;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            padding: 20px;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            width: 100%;
            max-width: 480px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 500;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.2);
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .details-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            text-align: left;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 15px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-top: 14px;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .detail-label {
            color: var(--text-muted);
        }

        .detail-value {
            font-weight: 500;
        }

        .detail-value.amount {
            font-size: 20px;
            font-weight: 700;
            color: #f59e0b;
        }

        .provider-icon {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .provider-wave {
            background: #1e3a8a;
            color: #60a5fa;
        }

        .provider-orange_money {
            background: #7c2d12;
            color: #fb923c;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            margin-bottom: 12px;
        }

        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            background: var(--success-hover);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: transparent;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.08);
            border-color: #ef4444;
        }

        .footer-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">ProsArtisan</div>
        <div class="status-badge">Simulateur de Paiement</div>

        <div class="details-card">
            <div class="detail-row">
                <span class="detail-label">Opérateur</span>
                <span class="detail-value">
                    <span class="provider-icon provider-{{ $transaction->provider->value }}">
                        {{ $transaction->provider->value }}
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">N° de Transaction</span>
                <span class="detail-value">#{{ $transaction->id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mission ID</span>
                <span class="detail-value">#{{ $transaction->mission_id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Description</span>
                <span class="detail-value">{{ $transaction->metadata['description'] ?? 'Paiement acompte' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Montant à payer</span>
                <span class="detail-value amount">{{ number_format($transaction->montant, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        <form action="{{ route('payment.mock.validate') }}" method="POST">
            @csrf
            <input type="hidden" name="transaction_id" value="{{ $transaction->id }}">
            
            <button type="submit" name="action" value="confirm" class="btn btn-success">
                Confirmer le paiement
            </button>
            
            <button type="submit" name="action" value="fail" class="btn btn-danger">
                Annuler / Échouer le paiement
            </button>
        </form>

        <p class="footer-note">
            Cette page s'affiche car vous êtes en mode de développement local sans clés d'API configurées pour Wave ou Orange Money.
        </p>
    </div>
</body>
</html>
