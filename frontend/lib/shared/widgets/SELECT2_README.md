# CustomSelect2 Widget

Un composant Select2-like pour Flutter avec fonctionnalité de recherche avancée, inspiré du plugin Select2 de jQuery.

## Fonctionnalités

- ✅ Recherche en temps réel dans les options
- ✅ Support du mode simple et multi-sélection
- ✅ Interface moderne et personnalisable
- ✅ Support du mode sombre
- ✅ Validation de formulaire intégrée
- ✅ Icônes personnalisables
- ✅ Rendu d'items personnalisé
- ✅ État vide personnalisé
- ✅ Support des objets complexes

## Installation

Le package `dropdown_search` est déjà ajouté dans `pubspec.yaml`:

```yaml
dependencies:
  dropdown_search: ^5.0.6
```

Exécutez:
```bash
flutter pub get
```

## Utilisation de base

### Select simple avec recherche

```dart
import 'package:frontend/shared/widgets/custom_select2.dart';

CustomSelect2<String>(
  selectedItem: selectedCountry,
  items: ['France', 'Côte d\'Ivoire', 'Sénégal'],
  itemAsString: (item) => item,
  label: 'Pays',
  hint: 'Sélectionner un pays',
  prefixIcon: Icons.flag,
  showSearchBox: true,
  searchHint: 'Rechercher un pays...',
  onChanged: (value) {
    setState(() {
      selectedCountry = value;
    });
  },
)
```

### Multi-sélection

```dart
CustomMultiSelect2<String>(
  selectedItems: selectedLanguages,
  items: ['Français', 'Anglais', 'Espagnol'],
  itemAsString: (item) => item,
  label: 'Langues parlées',
  hint: 'Sélectionner des langues',
  prefixIcon: Icons.language,
  onChanged: (values) {
    setState(() {
      selectedLanguages = values;
    });
  },
)
```

### Avec objets personnalisés

```dart
// Modèle de données
class Sector {
  final int id;
  final String name;
  final String code;
  
  Sector({required this.id, required this.name, required this.code});
}

// Utilisation
CustomSelect2<Sector>(
  selectedItem: selectedSector,
  items: sectors,
  itemAsString: (sector) => sector.name,
  label: 'Secteur d\'activité',
  hint: 'Sélectionner un secteur',
  prefixIcon: Icons.category,
  showSearchBox: true,
  searchHint: 'Rechercher un secteur...',
  onChanged: (value) {
    setState(() {
      selectedSector = value;
    });
  },
)
```

### Avec validation

```dart
CustomSelect2<String>(
  selectedItem: selectedItem,
  items: items,
  itemAsString: (item) => item,
  label: 'Champ requis',
  isRequired: true,
  validator: (value) {
    if (value == null) {
      return 'Ce champ est obligatoire';
    }
    return null;
  },
  onChanged: (value) {
    setState(() {
      selectedItem = value;
    });
  },
)
```

### Avec rendu personnalisé

```dart
CustomSelect2<Map<String, dynamic>>(
  selectedItem: selectedOption,
  items: [
    {'id': 1, 'name': 'Option 1', 'icon': Icons.star},
    {'id': 2, 'name': 'Option 2', 'icon': Icons.favorite},
  ],
  itemAsString: (item) => item['name'] as String,
  itemBuilder: (context, item, isSelected) {
    return Container(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Icon(
            item['icon'] as IconData,
            color: isSelected ? Colors.blue : Colors.grey,
          ),
          const SizedBox(width: 12),
          Text(
            item['name'] as String,
            style: TextStyle(
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  },
  onChanged: (value) {
    setState(() {
      selectedOption = value;
    });
  },
)
```

### Mode sombre

```dart
CustomSelect2<String>(
  selectedItem: selectedItem,
  items: items,
  itemAsString: (item) => item,
  isDarkMode: true,
  onChanged: (value) {
    setState(() {
      selectedItem = value;
    });
  },
)
```

## Paramètres

### CustomSelect2

| Paramètre | Type | Description | Défaut |
|-----------|------|-------------|--------|
| `selectedItem` | `T?` | Item actuellement sélectionné | `null` |
| `items` | `List<T>` | Liste des items disponibles | Requis |
| `itemAsString` | `String Function(T)` | Fonction pour convertir l'item en string | Requis |
| `onChanged` | `void Function(T?)?` | Callback lors du changement | `null` |
| `label` | `String?` | Label du champ | `null` |
| `hint` | `String?` | Texte d'indication | `null` |
| `prefixIcon` | `IconData?` | Icône de préfixe | `null` |
| `enabled` | `bool` | Active/désactive le champ | `true` |
| `showSearchBox` | `bool` | Affiche la barre de recherche | `true` |
| `validator` | `String? Function(T?)?` | Fonction de validation | `null` |
| `isRequired` | `bool` | Marque le champ comme requis | `false` |
| `searchHint` | `String` | Texte de la barre de recherche | `'Rechercher...'` |
| `itemBuilder` | `Widget Function(BuildContext, T, bool)?` | Builder personnalisé pour les items | `null` |
| `isDarkMode` | `bool` | Active le mode sombre | `false` |

### CustomMultiSelect2

Mêmes paramètres que `CustomSelect2`, sauf:

| Paramètre | Type | Description |
|-----------|------|-------------|
| `selectedItems` | `List<T>` | Items actuellement sélectionnés |
| `onChanged` | `void Function(List<T>)` | Callback avec la liste des items sélectionnés |

## Exemples d'utilisation dans le projet

### 1. Sélection de secteur (search_filter_screen.dart)

```dart
CustomSelect2<dynamic>(
  selectedItem: _searchController.selectedSectorId.value == null
      ? null
      : _searchController.sectors.firstWhereOrNull(
          (s) => s.id == _searchController.selectedSectorId.value,
        ),
  items: [null, ..._searchController.sectors],
  itemAsString: (item) => item == null ? 'Tous les secteurs' : item.name,
  hint: 'Sélectionner un secteur',
  prefixIcon: Icons.category_outlined,
  showSearchBox: true,
  searchHint: 'Rechercher un secteur...',
  onChanged: (value) {
    _searchController.setSectorFilter(value?.id);
  },
)
```

### 2. Sélection de métier

```dart
CustomSelect2<dynamic>(
  selectedItem: _searchController.selectedTradeId.value == null
      ? null
      : _searchController.trades.firstWhereOrNull(
          (t) => t.id == _searchController.selectedTradeId.value,
        ),
  items: [null, ..._searchController.trades],
  itemAsString: (item) => item == null 
      ? 'Tous les métiers du secteur' 
      : item.name,
  hint: 'Tous les métiers',
  prefixIcon: Icons.work_outline,
  showSearchBox: true,
  searchHint: 'Rechercher un métier...',
  onChanged: (value) {
    _searchController.setTradeFilter(value?.id);
  },
)
```

### 3. Filtre de score avec items personnalisés

```dart
final scoreOptions = [
  {'value': null, 'label': 'Pas de filtre'},
  {'value': 50, 'label': '50+ points'},
  {'value': 70, 'label': '70+ points'},
  {'value': 85, 'label': '85+ points'},
];

CustomSelect2<Map<String, dynamic>>(
  selectedItem: scoreOptions.firstWhere(
    (opt) => opt['value'] == _searchController.minScore.value,
    orElse: () => scoreOptions[0],
  ),
  items: scoreOptions,
  itemAsString: (item) => item['label'] as String,
  hint: 'Pas de filtre',
  prefixIcon: Icons.star_outline,
  showSearchBox: false,
  itemBuilder: (context, item, isSelected) {
    final value = item['value'] as int?;
    final label = item['label'] as String;
    
    return Container(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          if (value != null)
            const Icon(Icons.star, size: 18, color: Colors.amber),
          const SizedBox(width: 8),
          Expanded(child: Text(label)),
          if (isSelected)
            const Icon(Icons.check_circle, color: Colors.blue),
        ],
      ),
    );
  },
  onChanged: (value) {
    _searchController.setMinScore(value?['value'] as int?);
  },
)
```

## Bonnes pratiques

1. **Utilisez `showSearchBox: false`** pour les listes courtes (< 5 items)
2. **Fournissez un `searchHint` descriptif** pour améliorer l'UX
3. **Utilisez `itemBuilder`** pour des rendus complexes avec icônes ou badges
4. **Ajoutez une option "null"** au début de la liste pour "Tous" ou "Aucun"
5. **Validez les champs requis** avec `isRequired: true` et `validator`
6. **Adaptez `isDarkMode`** selon le thème de votre app

## Dépannage

### Le dropdown ne s'affiche pas correctement

Assurez-vous que le widget est dans un conteneur avec une largeur définie ou utilisez `Expanded` dans une Row.

### La recherche ne fonctionne pas

Vérifiez que `showSearchBox: true` et que `itemAsString` retourne bien une chaîne de caractères.

### Les items ne se sélectionnent pas

Assurez-vous que `onChanged` met bien à jour l'état avec `setState()` ou un state manager.

## Support

Pour plus d'exemples, consultez `custom_select2_example.dart`.
