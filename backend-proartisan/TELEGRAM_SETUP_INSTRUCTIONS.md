# 📱 Instructions de configuration Telegram pour ProsArtisan

## ⚠️ Problème actuel

Votre configuration actuelle dans `.env` :

```env
TELEGRAM_BOT_TOKEN='8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE'
TELEGRAM_CHAT_ID='8715763356'  ← INCORRECT
```

**Erreur** : Le `TELEGRAM_CHAT_ID` est identique à l'ID du bot. Les bots ne peuvent pas s'envoyer de messages.

---

## ✅ Solution en 3 étapes

### Étape 1 : Trouver votre bot dans Telegram

1. Ouvrez l'application Telegram sur votre téléphone ou ordinateur
2. Dans la barre de recherche, cherchez votre bot (vous devriez avoir son nom depuis BotFather)
3. Ou utilisez ce lien direct : `https://t.me/[NOM_DE_VOTRE_BOT]`

### Étape 2 : Démarrer une conversation

1. Cliquez sur le bouton **"Start"** ou **"Démarrer"**
2. Envoyez n'importe quel message, par exemple : `Hello` ou `Test`

### Étape 3 : Récupérer votre Chat ID

Exécutez cette commande dans votre terminal :

```bash
cd backend-proartisan
curl "https://api.telegram.org/bot8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE/getUpdates"
```

Vous verrez une réponse JSON comme celle-ci :

```json
{
  "ok": true,
  "result": [
    {
      "update_id": 123456789,
      "message": {
        "message_id": 1,
        "from": {
          "id": 987654321,
          "first_name": "Votre Nom"
        },
        "chat": {
          "id": 987654321,  ← COPIEZ CE NOMBRE
          "type": "private"
        },
        "text": "Hello"
      }
    }
  ]
}
```

Le nombre dans `"chat": {"id": ...}` est votre **Chat ID**.

### Étape 4 : Mettre à jour le .env

Ouvrez le fichier `backend-proartisan/.env` et modifiez :

```env
TELEGRAM_CHAT_ID='987654321'  # Remplacez par votre vrai Chat ID
```

**Important** : Supprimez les guillemets si vous préférez :

```env
TELEGRAM_CHAT_ID=987654321
```

### Étape 5 : Tester

```bash
cd backend-proartisan

# Test rapide
php artisan tinker --execute="Http::asForm()->post('https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage', ['chat_id' => env('TELEGRAM_CHAT_ID'), 'text' => '✅ ProsArtisan configuré avec succès !'])->body()"

# Ou utilisez le script de test
php test_telegram.php
```

Si tout fonctionne, vous recevrez un message sur Telegram ! 🎉

---

## 🔧 Alternative : Utiliser un bot helper

Si vous avez du mal à récupérer votre Chat ID, utilisez un bot Telegram existant :

1. Dans Telegram, recherchez : `@userinfobot` ou `@get_id_bot`
2. Démarrez une conversation avec ce bot
3. Il vous donnera immédiatement votre Chat ID
4. Copiez ce nombre dans votre `.env`

---

## 📋 Checklist de vérification

- [ ] J'ai ouvert Telegram et trouvé mon bot
- [ ] J'ai cliqué sur "Start" et envoyé un message
- [ ] J'ai exécuté la commande `curl` pour récupérer les updates
- [ ] J'ai copié le Chat ID depuis la réponse JSON
- [ ] J'ai mis à jour `TELEGRAM_CHAT_ID` dans le `.env`
- [ ] J'ai testé l'envoi avec Tinker
- [ ] Je reçois bien les messages sur Telegram

---

## 🆘 Besoin d'aide ?

Si vous rencontrez des problèmes, exécutez ce diagnostic :

```bash
cd backend-proartisan
php artisan tinker
```

Puis dans Tinker :

```php
// Vérifier la configuration
echo "Bot Token: " . env('TELEGRAM_BOT_TOKEN') . "\n";
echo "Chat ID: " . env('TELEGRAM_CHAT_ID') . "\n";

// Vérifier que le bot existe
$me = Http::get("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getMe")->json();
echo "Bot username: @" . $me['result']['username'] . "\n";

// Vérifier les messages reçus
$updates = Http::get("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/getUpdates")->json();
print_r($updates);
```

---

## 📚 Fichiers de référence

- `test_telegram.php` : Script de test complet
- `TELEGRAM_TINKER_COMMANDS.md` : Commandes Tinker pour tester
- `GET_TELEGRAM_CHAT_ID.md` : Guide détaillé pour obtenir le Chat ID

---

## 🎯 Prochaines étapes

Une fois la configuration terminée, le système enverra automatiquement des alertes Telegram pour :

- ✅ Erreurs critiques (niveau Error)
- ✅ Exceptions non gérées
- ✅ Problèmes de paiement
- ✅ Fraudes détectées (GPS J-Code)
- ✅ Litiges ouverts

Vous pouvez tester le logger avec :

```php
Log::channel('telegram_bot')->error('Test alerte ProsArtisan', [
    'user_id' => 123,
    'action' => 'test',
]);
```
