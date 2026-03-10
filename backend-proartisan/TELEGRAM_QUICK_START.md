# ⚡ Telegram Quick Start - ProsArtisan

## 🚀 Configuration en 2 minutes

### Votre bot : @ProsArtisanBot

---

## Étape 1 : Ouvrez Telegram

Cliquez sur ce lien : **https://t.me/ProsArtisanBot**

Ou recherchez `@ProsArtisanBot` dans Telegram.

---

## Étape 2 : Démarrez la conversation

1. Cliquez sur **"Start"**
2. Envoyez : `Hello`

---

## Étape 3 : Récupérez votre Chat ID

```bash
cd backend-proartisan
php get_chat_id.php
```

Le script vous donnera une ligne comme :

```
TELEGRAM_CHAT_ID=987654321
```

---

## Étape 4 : Mettez à jour .env

Ouvrez `backend-proartisan/.env` et remplacez :

```env
TELEGRAM_CHAT_ID='8715763356'  ← ANCIEN (incorrect)
```

Par :

```env
TELEGRAM_CHAT_ID=987654321  ← NOUVEAU (votre vrai Chat ID)
```

---

## Étape 5 : Testez

```bash
php test_telegram.php
```

Vous devriez recevoir 3 messages de test sur Telegram ! ✅

---

## 🧪 Test rapide one-liner

```bash
php artisan tinker --execute="Log::channel('telegram_bot')->error('✅ ProsArtisan configuré');"
```

---

## 📚 Documentation complète

- **README_TELEGRAM.md** : Guide complet avec tous les détails
- **TELEGRAM_SETUP_INSTRUCTIONS.md** : Instructions détaillées
- **TELEGRAM_TINKER_COMMANDS.md** : Commandes de test
- **GET_TELEGRAM_CHAT_ID.md** : Méthodes alternatives

---

## 🆘 Problème ?

Exécutez le diagnostic :

```bash
php get_chat_id.php
```

Le script vous guidera automatiquement.

---

## ✅ C'est tout !

Une fois configuré, vous recevrez automatiquement des alertes Telegram pour :

- 🚨 Erreurs critiques
- ⚠️ Exceptions
- 🔒 Fraudes GPS détectées
- ⚖️ Litiges ouverts
- 💰 Paiements importants

---

**Temps estimé : 2 minutes** ⏱️
