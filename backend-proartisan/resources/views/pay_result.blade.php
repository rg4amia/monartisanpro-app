<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProsArtisan — Validation du Paiement</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --glass-bg: rgba(30, 41, 59, 0.75);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --success: #10b981;
            --danger: #ef4444;
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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 36px;
        }

        .icon-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 2px solid rgba(16, 185, 129, 0.4);
        }

        .icon-failed {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        .title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .protocol-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .protocol-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .protocol-item {
            display: flex;
            align-items: center;
            font-size: 13px;
            margin-bottom: 8px;
            color: #cbd5e1;
        }

        .protocol-item:last-child {
            margin-bottom: 0;
        }

        .protocol-icon {
            margin-right: 10px;
            font-size: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        @if($statusStr === 'success')
            <div class="icon-circle icon-success">✓</div>
            <h1 class="title">Paiement Validé !</h1>
            <p class="subtitle">{{ $message }}</p>

            <div class="protocol-box">
                <div class="protocol-title">Protocoles de sécurité appliqués</div>
                <div class="protocol-item">
                    <span class="protocol-icon">🔒</span>
                    <span>Transaction #{{ $transaction->id }} enregistrée & chiffrée</span>
                </div>
                <div class="protocol-item">
                    <span class="protocol-icon">⚖️</span>
                    <span>Séquestre fragmenté & verrouillé automatiquement</span>
                </div>
                <div class="protocol-item">
                    <span class="protocol-icon">📲</span>
                    <span>Notification temps réel envoyée à l'artisan</span>
                </div>
            </div>
        @else
            <div class="icon-circle icon-failed">✕</div>
            <h1 class="title">Paiement non finalisé</h1>
            <p class="subtitle">{{ $message }}</p>
        @endif

        <a href="intent://payment-result?transaction_id={{ $transaction->id }}&status={{ $statusStr }}&mission_id={{ $transaction->mission_id }}#Intent;scheme=prosartisan;package=com.prosartisan.app;end" onclick="triggerReturn(); return true;" class="btn btn-primary" id="return-btn">
            ⬅️ Retourner sur ProsArtisan
        </a>
    </div>

    <script>
        const transactionId = "{{ $transaction->id }}";
        const statusStr = "{{ $statusStr }}";
        const missionId = "{{ $transaction->mission_id }}";

        const intentUrl = "intent://payment-result?transaction_id=" + transactionId + "&status=" + statusStr + "&mission_id=" + missionId + "#Intent;scheme=prosartisan;package=com.prosartisan.app;end";
        const customSchemeUrl = "prosartisan://payment-result?transaction_id=" + transactionId + "&status=" + statusStr + "&mission_id=" + missionId;

        function triggerReturn() {
            window.location.href = intentUrl;
            setTimeout(function() {
                window.location.href = customSchemeUrl;
            }, 300);
        }

        // Auto trigger return on page load
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(triggerReturn, 600);
        });
    </script>
</body>
</html>
