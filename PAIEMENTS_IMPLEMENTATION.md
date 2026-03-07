# 💰 Implémentation des Paiements Wave & Orange Money CI

## 📋 Vue d'ensemble

L'intégration des paiements mobiles Wave CI et Orange Money CI a été **complètement implémentée** pour le projet ProsArtisan. Cette implémentation permet aux clients de payer les acomptes de missions via les deux principaux providers de mobile money en Côte d'Ivoire.

---

## ✅ Composants implémentés

### 1. **Base de données**

#### Migrations créées :
- `2026_03_07_195554_add_payment_fields_to_transactions_table.php`
  - Champs Wave : `wave_checkout_id`, `wave_payment_id`, `wave_client_reference`
  - Champs Orange Money : `orange_order_id`, `orange_payment_token`, `orange_tx_reference`
  - Champs communs : `webhook_payload`, `paid_at`, `failed_at`, `error_message`, `client_phone`, `metadata`

- `2026_03_07_195617_create_wallet_transactions_table.php`
  - Table complète pour tracer toutes les opérations wallet (crédit, débit, blocage, déblocage)
  - Référence unique, soldes avant/après, métadonnées

- `2026_03_07_201119_add_ratio_materiaux_to_devis_table.php`
  - Champ `ratio_materiaux` (DECIMAL 5,4) pour fragmentation séquestre
  - Valeur par défaut : 0.6500 (65% matériaux, 35% main d'œuvre)

### 2. **Models & Enums**

#### Enums créés :
- `App\Enums\PaymentProvider` : WAVE, ORANGE_MONEY, VIREMENT_BANCAIRE
- `App\Enums\PaymentStatus` : EN_ATTENTE, CONFIRME, ECHOUE
- `App\Enums\WalletType` : WALLET_MATERIAUX, WALLET_MO
- `App\Enums\WalletOperation` : CREDIT, DEBIT, BLOCAGE, DEBLOCAGE

#### Models mis à jour :
- `Transaction` : Ajout casts enums, scopes (wave, orangeMoney, confirmed), helpers (isWave, isPending, etc.)
- `WalletTransaction` : Nouveau model avec génération automatique de référence unique
- `Devis` : Ajout champ `ratio_materiaux` au fillable

### 3. **Services métier**

#### `App\Services\WalletService`
**Méthodes principales :**
- `credit(User, WalletType, montant)` : Créditer un wallet avec transaction atomique
- `debit(User, WalletType, montant)` : Débiter un wallet avec vérification solde
- `transfer(...)` : Transférer entre wallets
- `fragmentEscrow(Mission, ...)` : **Fragmentation automatique du séquestre** selon ratio immuable
- `releaseJalon(Jalon)` : Libération main d'œuvre après validation jalon
- `payFournisseur(JCode)` : Paiement fournisseur via J-Code

**Garanties :**
- Transactions DB atomiques avec `lockForUpdate()`
- Traçabilité complète via `WalletTransaction`
- Logging détaillé de toutes les opérations

#### `App\Services\WaveService`
**Méthodes principales :**
- `createCheckout(montant, phone, description)` : Créer session de paiement Wave
- `checkPaymentStatus(checkoutId)` : Vérifier statut d'un paiement
- `validateWebhookSignature(payload, signature)` : Validation HMAC des webhooks
- `processWebhook(data)` : Traitement automatique des callbacks Wave
- `sendMoney(phone, montant, description)` : Virements sortants vers artisans/fournisseurs

**Configuration requise** (dans `.env`) :
```env
WAVE_API_URL=https://api.wave.com/v1
WAVE_API_KEY=your_api_key
WAVE_SECRET_KEY=your_secret_key
WAVE_WEBHOOK_SECRET=your_webhook_secret
WAVE_CURRENCY=XOF
```

#### `App\Services\OrangeMoneyService`
**Méthodes principales :**
- `getAccessToken()` : Authentification OAuth
- `createPayment(montant, phone, description)` : Créer Web Payment Orange Money
- `checkPaymentStatus(orderId, paymentToken)` : Vérifier statut
- `processNotification(data)` : Traitement des callbacks Orange Money
- `sendMoney(phone, montant, description)` : Cash-in vers artisans/fournisseurs

**Configuration requise** (dans `.env`) :
```env
ORANGE_MONEY_API_URL=https://api.orange.com/orange-money-webpay/ci/v1
ORANGE_MONEY_MERCHANT_KEY=your_merchant_key
ORANGE_MONEY_MERCHANT_ID=your_merchant_id
ORANGE_MONEY_AUTH_HEADER=Base64(client_id:client_secret)
ORANGE_MONEY_CURRENCY=XOF
```

### 4. **Controllers API**

#### `App\Http\Controllers\Api\V1\PaymentController`
**Endpoints implémentés :**

1. **POST** `/api/v1/payments/initiate`
   - Initie un paiement Wave ou Orange Money
   - Body : `mission_id`, `montant`, `provider` (wave|orange_money), `phone`
   - Retourne : `payment_url`, `checkout_id`/`order_id`, `transaction_id`

2. **GET** `/api/v1/payments/{transaction}/status`
   - Vérifie le statut d'un paiement
   - Déclenche fragmentation séquestre si confirmé

3. **GET** `/api/v1/payments/history`
   - Historique des paiements de l'utilisateur
   - Query param : `limit` (défaut: 20)

#### `App\Http\Controllers\Api\V1\WebhookController`
**Endpoints webhooks :**

1. **POST** `/api/webhooks/wave`
   - Callback Wave (validation signature HMAC SHA-256)
   - Déclenche fragmentation séquestre automatique

2. **POST** `/api/webhooks/orange-money`
   - Callback Orange Money
   - Déclenche fragmentation séquestre automatique

### 5. **Routes API**

Routes ajoutées dans `routes/api.php` :

```php
// Webhooks (publics)
Route::post('/webhooks/wave', [WebhookController::class, 'wave']);
Route::post('/webhooks/orange-money', [WebhookController::class, 'orangeMoney']);

// Paiements (authentifiés)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::get('/payments/{transaction}/status', [PaymentController::class, 'checkStatus']);
    Route::get('/payments/history', [PaymentController::class, 'history']);
});
```

---

## 🔄 Workflow complet de paiement

### Phase 1 : Initiation (Client)

```
Client Flutter App
      ↓
POST /api/v1/payments/initiate
  { mission_id, montant, provider, phone }
      ↓
PaymentController::initiatePayment()
      ↓
Création Transaction (statut: EN_ATTENTE)
      ↓
Appel WaveService ou OrangeMoneyService
      ↓
Retour payment_url au client
      ↓
Client redirigé vers app Wave/Orange Money
```

### Phase 2 : Paiement mobile (Externe)

```
Utilisateur confirme dans app Wave/OM
      ↓
Provider traite le paiement
      ↓
Provider appelle webhook ProsArtisan
```

### Phase 3 : Callback webhook (Automatique)

```
POST /webhooks/wave (ou /webhooks/orange-money)
      ↓
WebhookController::wave() ou ::orangeMoney()
      ↓
Validation signature (Wave uniquement)
      ↓
Mise à jour Transaction (statut: CONFIRME, paid_at)
      ↓
handlePaymentSuccess()
      ↓
WalletService::fragmentEscrow()
      ↓
Création 2 WalletTransaction (wallet_materiaux + wallet_mo)
      ↓
Mission.status → 'financee'
```

### Phase 4 : Vérification polling (Optionnel)

```
Client Flutter App polling
      ↓
GET /api/v1/payments/{transaction}/status
      ↓
PaymentController::checkStatus()
      ↓
Si pas encore confirmé : appel checkPaymentStatus() du provider
      ↓
Si confirmé : déclenche fragmentation séquestre
```

---

## 🛡️ Sécurité & Anti-fraude

### Validation signatures webhook
- **Wave** : HMAC SHA-256 avec `WAVE_WEBHOOK_SECRET`
- **Orange Money** : Pas de signature (validation IP recommandée en production)

### Transactions atomiques
- Tous les crédits/débits wallets utilisent `DB::transaction()` + `lockForUpdate()`
- Garantit cohérence des soldes même en cas de concurrence

### Traçabilité
- Chaque opération wallet génère une ligne `WalletTransaction`
- Logs détaillés à chaque étape (Laravel Log facade)
- Champ `webhook_payload` conserve les données brutes des providers

---

## 🧪 Tests recommandés

### Tests unitaires à créer :
1. `WalletServiceTest` : crédit/débit/transfer/fragmentEscrow
2. `WaveServiceTest` : mock API Wave, validation signatures
3. `OrangeMoneyServiceTest` : mock API Orange Money
4. `PaymentControllerTest` : endpoints avec utilisateurs authentifiés
5. `WebhookControllerTest` : simulation callbacks Wave/OM

### Tests d'intégration :
1. Workflow complet paiement Wave (sandbox)
2. Workflow complet paiement Orange Money (sandbox)
3. Gestion erreurs (paiement échoué, timeout, etc.)
4. Rejeu de webhooks (idempotence)

---

## 📦 Dépendances

Aucune dépendance externe ajoutée. Utilise uniquement :
- `Illuminate\Support\Facades\Http` (Laravel HTTP client)
- `Illuminate\Support\Facades\DB` (transactions)
- `Illuminate\Support\Facades\Log` (logging)

---

## 🚀 Prochaines étapes

### Frontend Flutter
1. Créer écran de sélection provider (Wave/Orange Money)
2. Implémenter redirection vers `payment_url`
3. Gérer retour depuis app mobile money
4. Polling `/payments/{transaction}/status`
5. Afficher confirmation/échec paiement

### Backend
1. Implémenter virements sortants (libération jalons, paiement fournisseurs)
2. Gérer remboursements en cas de litige
3. Implémenter rapports financiers pour admin
4. Ajouter monitoring transactions échouées
5. Créer dashboard wallet pour artisans

### Production
1. Configurer URLs webhooks chez Wave et Orange Money
2. Whitelister IPs des providers (firewall)
3. Configurer certificats SSL pour webhooks
4. Tester en environnement sandbox avant prod
5. Mettre en place alertes (paiements échoués, erreurs webhook)

---

## 📚 Documentation API providers

- **Wave CI** : https://developers.wave.com/
- **Orange Money CI** : https://developer.orange.com/apis/orange-money-webpay/

---

## ✨ Résumé

✅ **Migrations** : 3 migrations (transactions, wallet_transactions, ratio_materiaux)
✅ **Models** : Transaction, WalletTransaction, Devis mis à jour
✅ **Enums** : 4 enums (PaymentProvider, PaymentStatus, WalletType, WalletOperation)
✅ **Services** : WalletService, WaveService, OrangeMoneyService
✅ **Controllers** : PaymentController, WebhookController
✅ **Routes** : 5 routes API (3 paiements + 2 webhooks)
✅ **Configuration** : config/services.php complet
✅ **Workflow** : Fragmentation séquestre automatique après paiement

**Statut** : 🟢 **Prêt pour intégration frontend Flutter et tests sandbox**

---

**Date d'implémentation** : 7 mars 2026
**Auteur** : Claude Code Assistant
