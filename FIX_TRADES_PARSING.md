# Fix: Parsing des Métiers par Secteur

## 🐛 Problème Identifié

Lorsqu'on sélectionne un secteur dans le filtre de recherche, les métiers ne s'affichent pas dans le dropdown, même si l'API retourne correctement les données.

### Logs de l'Erreur
```
I/flutter: ║ "success": true,
I/flutter: ║ "data": {
I/flutter: ║     "sector": {id: 7, code: 7, name: ARTISANAT & MÉTIERS CRÉATIFS},
I/flutter: ║     "trades": [
I/flutter: ║         {id: 108, sector_id: 7, code: 108, name: BIJOUTIER},
I/flutter: ║         {id: 106, sector_id: 7, code: 106, name: CORDONNIER},
I/flutter: ║         ...
I/flutter: ║     ]
I/flutter: ║ }
```

**Symptôme:** Le dropdown "Métier" reste vide ou affiche "Aucun métier disponible"

## 🔍 Cause Racine

### Structure de Réponse Backend
Le backend retourne une structure imbriquée:
```json
{
  "success": true,
  "data": {
    "sector": {
      "id": 7,
      "code": "7",
      "name": "ARTISANAT & MÉTIERS CRÉATIFS"
    },
    "trades": [
      {"id": 108, "sector_id": 7, "code": "108", "name": "BIJOUTIER"},
      {"id": 106, "sector_id": 7, "code": "106", "name": "CORDONNIER"}
    ]
  }
}
```

### Code Frontend (Avant)
Le service essayait de parser `data` directement comme une liste:
```dart
return ApiResponse<List<Trade>>.fromJson(
  response.data,
  (json) => (json as List)  // ❌ ERREUR: json est un Map, pas une List
      .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
      .toList(),
);
```

**Problème:** `json` est un `Map<String, dynamic>` avec les clés `sector` et `trades`, pas une `List` directement.

## ✅ Solution Appliquée

### Code Corrigé
```dart
return ApiResponse<List<Trade>>.fromJson(
  response.data,
  (json) {
    // Backend returns {sector: {...}, trades: [...]}
    // We need to extract the trades array
    if (json is Map<String, dynamic> && json.containsKey('trades')) {
      return (json['trades'] as List)
          .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
          .toList();
    }
    // Fallback if data is already a list
    if (json is List) {
      return json
          .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
          .toList();
    }
    return [];
  },
);
```

### Logique de Parsing
1. **Vérifier si `json` est un Map** avec la clé `trades`
   - Si oui: extraire `json['trades']` et le parser
2. **Fallback:** Si `json` est déjà une List
   - Parser directement (pour compatibilité future)
3. **Défaut:** Retourner une liste vide si aucun cas ne correspond

## 🎯 Résultat Attendu

### Avant le Fix
```
Secteur: ARTISANAT & MÉTIERS CRÉATIFS
Métier: [Dropdown vide ou "Aucun métier disponible"]
```

### Après le Fix
```
Secteur: ARTISANAT & MÉTIERS CRÉATIFS
Métier: [Dropdown avec 7 options]
  - Tous les métiers du secteur
  - BIJOUTIER
  - CORDONNIER
  - MAROQUINIER
  - PEINTRE DÉCORATEUR
  - SCULPTEUR
  - STYLISTE
  - TAILLEUR / COUTURIER
```

## 🧪 Test de Validation

### Étapes
1. Hot Restart Flutter: `R`
2. Se connecter: `kouassi.yao@email.ci` / `password123`
3. Aller sur l'écran de recherche
4. Sélectionner "ARTISANAT & MÉTIERS CRÉATIFS"
5. Observer le dropdown "Métier"

### Résultat Attendu
- ✅ Indicateur de chargement s'affiche brièvement
- ✅ 7 métiers apparaissent dans le dropdown
- ✅ Option "Tous les métiers du secteur" en premier
- ✅ Métiers triés alphabétiquement

### Tester Tous les Secteurs

| Secteur | Nb Métiers Attendus |
|---------|---------------------|
| MÉCANIQUE & AUTOMOBILE | 3 |
| ÉLECTRICITÉ & ÉNERGIE | 1 |
| PLOMBERIE & FLUIDES | 1 |
| BÂTIMENT & TRAVAUX PUBLICS | 2 |
| MENUISERIE & BOIS | 1 |
| MÉTALLURGIE & SOUDURE | 1 |
| ARTISANAT & MÉTIERS CRÉATIFS | 7 |
| NUMÉRIQUE & TECHNIQUE | Variable |
| FROID, CLIMATISATION | 1 |
| SERVICES & MÉTIERS | 1 |
| SÉCURITÉ & INSTALLATION | Variable |
| ASSAINISSEMENT & EAU | Variable |

## 📝 Fichiers Modifiés

### `frontend/lib/core/network/trade_service.dart`
- Fonction `getTradesBySector()` mise à jour
- Parsing robuste avec extraction de `json['trades']`
- Fallback pour compatibilité

## 🔄 Compatibilité Backend

### Endpoint: `GET /sectors/{id}/trades`

**Réponse Actuelle:**
```json
{
  "success": true,
  "data": {
    "sector": {...},
    "trades": [...]
  }
}
```

**Compatible avec:**
- ✅ Structure actuelle (Map avec `trades`)
- ✅ Structure future potentielle (List directe)
- ✅ Cas d'erreur (retourne liste vide)

## 🚀 Déploiement

### Commandes
```bash
# Hot Restart Flutter
R  # (majuscule)

# Ou rebuild complet si nécessaire
flutter clean
flutter pub get
flutter run
```

### Vérification
```bash
# Vérifier les diagnostics
flutter analyze frontend/lib/core/network/trade_service.dart
```

## 📊 Impact

### Avant
- ❌ Dropdown métier vide
- ❌ Impossible de filtrer par métier spécifique
- ❌ Recherche limitée au secteur uniquement

### Après
- ✅ Dropdown métier fonctionnel
- ✅ Filtrage par métier spécifique possible
- ✅ Recherche flexible (secteur + métier)
- ✅ UX complète et intuitive

## 🐛 Debugging

### Si le Problème Persiste

#### 1. Vérifier les Logs
```dart
// Dans trade_service.dart, ajouter temporairement:
debugPrint('Response data: ${response.data}');
debugPrint('Data type: ${response.data['data'].runtimeType}');
```

#### 2. Vérifier le Backend
```bash
curl -X GET "https://prosartisan.net/api/v1/sectors/7/trades" \
  -H "Accept: application/json"
```

#### 3. Vérifier le Contrôleur
```dart
// Dans search_controller.dart
debugPrint('Trades loaded: ${trades.length}');
debugPrint('Trades: ${trades.map((t) => t.name).toList()}');
```

#### 4. Cache Flutter
```bash
flutter clean
flutter pub get
rm -rf build/
flutter run
```

## ✅ Checklist de Validation

- [ ] Hot Restart effectué
- [ ] Connexion réussie
- [ ] Secteur sélectionné
- [ ] Métiers chargés et affichés
- [ ] Dropdown fonctionnel
- [ ] Recherche par métier fonctionne
- [ ] Tous les secteurs testés
- [ ] Aucune erreur dans les logs

## 📚 Références

- **Backend Controller:** `backend/app/Http/Controllers/Api/V1/TradeController.php`
- **Frontend Service:** `frontend/lib/core/network/trade_service.dart`
- **Frontend Controller:** `frontend/lib/shared/controllers/search_controller.dart`
- **Frontend Screen:** `frontend/lib/features/search/presentation/screens/search_filter_screen.dart`

---

**Fix appliqué! Les métiers devraient maintenant s'afficher correctement dans le dropdown. 🎉**
