# 📱 Configuration Telegram pour ProsArtisan

## 🎯 Résumé rapide

Votre bot Telegram : **@ProsArtisanBot**

### Configuration actuelle (INCORRECTE)

```env
TELEGRAM_BOT_TOKEN='8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE' ✅
TELEGRAM_CHAT_ID='8715763356' ❌ (c'est l'ID du bot, pas du chat)
```

**Problème** : Les bots ne peuvent pas s'envoyer de messages. Vous devez obtenir votre Chat ID personnel.

---

## ⚡ Configuration en 3 minutes

### 1️⃣ Ouvrez Telegram et trouvez votre bot

- **Sur mobile** : Recherchez `@ProsArtisanBot` dans la barre de recherche
- **Sur desktop** : Cliquez sur ce lien → https://t.me/ProsArtisanBot

### 2️⃣ Démarrez la conversation

1. Cliquez sur le bouton **"Start"** ou **"Démarrer"**
2. Envoyez n'importe quel message : `Hello` ou `Test`

### 3️⃣ Récupérez votre Chat ID

Dans votre terminal, exécutez :

```bash
cd backend-proartisan
php get_chat_id.php
```

Le script va :
- ✅ Vérifier que le bot existe
- ✅ Récupérer votre Chat ID
- ✅ Vous donner la ligne exacte à ajouter dans `.env`
- ✅ Envoyer un message de test sur Telegram

### 4️⃣ Mettez à jour le .env

Copiez la ligne fournie par le script dans `backend-proartisan/.env` :

```env
TELEGRAM_CHAT_ID=987654321  # Votre vrai Chat ID
```

### 5️⃣ Testez

```bash
# Test complet
php test_telegram.php

# Ou test rapide via Tinker
php artisan tinker --execute="Log::channel('telegram_bot')->error('✅ Test ProsArtisan');"
```

Vous devriez recevoir un message sur Telegram ! 🎉

---

## 🔧 Scripts disponibles

| Script | Description |
|--------|-------------|
| `get_chat_id.php` | Récupère automatiquement votre Chat ID |
| `test_telegram.php` | Tests complets d'envoi de messages |
| `TELEGRAM_SETUP_INSTRUCTIONS.md` | Guide détaillé étape par étape |
| `TELEGRAM_TINKER_COMMANDS.md` | Commandes Tinker pour déboguer |
| `GET_TELEGRAM_CHAT_ID.md` | Méthodes alternatives pour obtenir le Chat ID |

---

## 🧪 Tests disponibles

### Test 1 : Script automatique (recommandé)

```bash
php get_chat_id.php
```

### Test 2 : Script de test complet

```bash
php test_telegram.php
```

### Test 3 : Via Tinker

```bash
php artisan tinker
```

Puis dans Tinker :

```php
// Test simple
Log::channel('telegram_bot')->error('Test depuis Tinker');

// Test avec contexte
Log::channel('telegram_bot')->error('Erreur de test', [
    'user_id' => 123,
    'action' => 'test_action',
]);

// Test avec exception
$exception = new \Exception('Test exception', 500);
Log::channel('telegram_bot')->error('Exception de test', [
    'exception' => $exception,
]);
```

### Test 4 : One-liner

```bash
php artisan tinker --execute="Http::asForm()->post('https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage', ['chat_id' => env('TELEGRAM_CHAT_ID'), 'text' => '✅ Test - ' . now()])->body()"
```

---

## 📊 Que va envoyer le système ?

Une fois configuré, le système enverra automatiquement des alertes Telegram pour :

### Erreurs critiques (niveau Error)

```
🚨 ProsArtisan API - Alerte

App: ProsArtisan
Env: local
Niveau: Error
Message: Erreur de connexion à la base de données
Heure UTC: 2026-03-09 14:30:00
```

### Exceptions non gérées

```
🚨 ProsArtisan API - Alerte

App: ProsArtisan
Env: production
Niveau: Error
Message: Erreur lors du paiement Wave

Exception:
App\Exceptions\PaymentException: Transaction échouée
/var/www/app/Services/WaveService.php:123

Heure UTC: 2026-03-09 14:30:00
```

### Exemples d'alertes métier

Vous pouvez ajouter des alertes personnalisées dans votre code :

```php
// Alerte fraude GPS J-Code
Log::channel('telegram_bot')->error('🚨 Fraude GPS détectée', [
    'jcode' => $jcode->code,
    'fournisseur_id' => $fournisseur->id,
    'distance' => $distance . ' mètres',
    'seuil' => '100 mètres',
]);

// Alerte litige ouvert
Log::channel('telegram_bot')->error('⚠️ Nouveau litige', [
    'mission_id' => $mission->id,
    'client_id' => $client->id,
    'artisan_id' => $artisan->id,
    'montant' => $mission->montant_total . ' FCFA',
]);

// Alerte paiement important
Log::channel('telegram_bot')->warning('💰 Paiement important', [
    'mission_id' => $mission->id,
    'montant' => $montant . ' FCFA',
    'artisan_id' => $artisan->id,
]);
```

---

## 🆘 Dépannage

### Erreur : "Forbidden: bots can't send messages to bots"

➡️ Vous utilisez l'ID du bot comme Chat ID. Suivez les étapes ci-dessus pour obtenir votre vrai Chat ID.

### Erreur : "Bad Request: chat not found"

➡️ Le Chat ID est incorrect. Vérifiez que vous avez bien envoyé un message au bot avant de récupérer le Chat ID.

### Erreur : "Unauthorized"

➡️ Le `TELEGRAM_BOT_TOKEN` est incorrect. Vérifiez qu'il n'y a pas d'espaces ou de guillemets en trop.

### Aucun message reçu

➡️ Vérifiez que :
1. Vous avez bien cliqué sur "Start" dans le chat avec le bot
2. Le Chat ID dans `.env` est correct
3. Le bot n'est pas bloqué

### Diagnostic complet

```bash
php artisan tinker
```

```php
// Vérifier la config
echo "Bot Token: " . env('TELEGRAM_BOT_TOKEN') . "\n";
echo "Chat ID: " . env('TELEGRAM_CHAT_ID') . "\n";

// Vérifier le bot
$me = Http::get("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getMe")->json();
echo "Bot: @" . $me['result']['username'] . "\n";

// Vérifier les messages
$updates = Http::get("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getUpdates")->json();
print_r($updates);

// Test d'envoi
$response = Http::asForm()->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'text' => '✅ Test diagnostic',
]);
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
```

---

## 📚 Documentation complète

- **TELEGRAM_SETUP_INSTRUCTIONS.md** : Guide détaillé avec captures d'écran
- **TELEGRAM_TINKER_COMMANDS.md** : Toutes les commandes Tinker disponibles
- **GET_TELEGRAM_CHAT_ID.md** : Méthodes alternatives pour obtenir le Chat ID

---

## ✅ Checklist finale

- [ ] J'ai ouvert Telegram
- [ ] J'ai trouvé @ProsArtisanBot
- [ ] J'ai cliqué sur "Start"
- [ ] J'ai envoyé un message
- [ ] J'ai exécuté `php get_chat_id.php`
- [ ] J'ai copié le Chat ID dans `.env`
- [ ] J'ai testé avec `php test_telegram.php`
- [ ] Je reçois bien les messages sur Telegram

---

## 🎯 Prochaines étapes

Une fois la configuration terminée :

1. **Testez les alertes** : `php test_telegram.php`
2. **Intégrez dans votre code** : Utilisez `Log::channel('telegram_bot')->error(...)` pour les alertes importantes
3. **Configurez les niveaux** : Modifiez `config/logging.php` pour ajuster les niveaux de log
4. **Ajoutez des alertes métier** : Fraudes GPS, litiges, paiements importants, etc.

---

## 💡 Astuce : Utiliser un groupe pour l'équipe

Si vous travaillez en équipe, créez un groupe Telegram :

1. Créez un groupe dans Telegram
2. Ajoutez @ProsArtisanBot au groupe
3. Envoyez un message dans le groupe
4. Exécutez `php get_chat_id.php`
5. Le Chat ID du groupe commencera par `-` (ex: `-1001234567890`)
6. Mettez à jour `.env` avec ce Chat ID

Toute l'équipe recevra les alertes ! 👥

---

**Besoin d'aide ?** Consultez les fichiers de documentation ou exécutez `php get_chat_id.php` pour un diagnostic automatique.
