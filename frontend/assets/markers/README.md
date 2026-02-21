# Marqueurs de Carte SVG

## 📍 Fichiers Requis

Ce dossier doit contenir les fichiers SVG suivants:

### 1. marker-fine.svg
**Utilisation**: Marqueur pour la position du client

**Spécifications**:
- Taille recommandée: 48x48px
- Format: SVG optimisé
- Couleur: Bleu (#4F46E5) ou personnalisée
- Style: Pin de localisation ou cercle avec icône

**Exemple de contenu SVG**:
```svg
<svg width="48" height="48" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
  <circle cx="24" cy="24" r="20" fill="#4F46E5" stroke="white" stroke-width="3"/>
  <path d="M24 16c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8z" fill="white"/>
</svg>
```

### 2. artisan-marker.svg
**Utilisation**: Marqueur pour les artisans

**Spécifications**:
- Taille recommandée: 48x48px
- Format: SVG optimisé
- Couleur: Sera colorée dynamiquement (doré ou bleu)
- Style: Pin de localisation avec icône construction

**Exemple de contenu SVG**:
```svg
<svg width="48" height="56" viewBox="0 0 48 56" xmlns="http://www.w3.org/2000/svg">
  <!-- Pin shape -->
  <path d="M24 0C13.5 0 5 8.5 5 19c0 14 19 37 19 37s19-23 19-37c0-10.5-8.5-19-19-19z" 
        fill="currentColor" stroke="white" stroke-width="2"/>
  <!-- Icon circle -->
  <circle cx="24" cy="19" r="10" fill="white"/>
  <!-- Construction icon -->
  <path d="M24 14l-4 4h8l-4-4z" fill="currentColor"/>
</svg>
```

## 🎨 Coloration Dynamique

Le fichier `artisan-marker.svg` sera coloré automatiquement:
- 🟡 **Doré** (#FBBF24) pour les artisans à moins de 2km
- 🔵 **Bleu** (#5B7FFF) pour les artisans à plus de 2km

Pour que la coloration fonctionne, utilisez `currentColor` ou des couleurs qui peuvent être remplacées.

## 🛠️ Création des Marqueurs

### Option 1: Figma
1. Créez un design 48x48px
2. Exportez en SVG
3. Optimisez avec [SVGOMG](https://jakearchibald.github.io/svgomg/)
4. Placez dans ce dossier

### Option 2: Outils en ligne
- [Flaticon](https://www.flaticon.com/) - Icônes gratuites
- [Icons8](https://icons8.com/) - Générateur d'icônes
- [SVG Repo](https://www.svgrepo.com/) - Repository SVG

### Option 3: Code SVG
Créez directement le code SVG comme dans les exemples ci-dessus.

## ✅ Vérification

Après avoir ajouté les fichiers:

```bash
# Vérifier que les fichiers existent
ls -la frontend/assets/markers/

# Devrait afficher:
# marker-fine.svg
# artisan-marker.svg
# README.md
```

## 🔄 Fallback

Si les fichiers SVG ne sont pas trouvés, l'application utilisera automatiquement des marqueurs simples (cercles colorés) comme fallback.

## 📏 Dimensions Recommandées

| Marqueur | Largeur | Hauteur | Ratio |
|----------|---------|---------|-------|
| Client   | 48px    | 48px    | 1:1   |
| Artisan  | 48px    | 56px    | 6:7   |

## 🎯 Exemples de Design

### Style Moderne
- Formes arrondies
- Ombres subtiles
- Couleurs vives
- Icônes simples

### Style Minimaliste
- Formes géométriques simples
- Pas d'ombres
- Couleurs plates
- Contours nets

### Style Réaliste
- Dégradés
- Ombres portées
- Détails fins
- Perspective 3D

## 📝 Notes Importantes

1. **Optimisation**: Utilisez SVGOMG pour réduire la taille des fichiers
2. **Compatibilité**: Testez avec flutter_svg
3. **Simplicité**: Évitez les SVG trop complexes
4. **Performance**: Gardez les fichiers < 10KB

## 🚀 Après Ajout

1. Relancez `flutter pub get`
2. Redémarrez l'application
3. Testez sur la carte
4. Vérifiez les logs pour confirmer le chargement

## 📞 Support

Si vous avez besoin d'aide pour créer les marqueurs, consultez:
- `SVG_MARKERS_GUIDE.md` - Guide complet
- `MAP_MARKERS_IMPLEMENTATION.md` - Documentation technique
