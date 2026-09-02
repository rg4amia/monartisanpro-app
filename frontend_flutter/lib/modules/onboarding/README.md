# Module Onboarding

## Vue d'ensemble

Ce module gère le splash screen et le flow d'onboarding de l'application ProsArtisan, basé sur les designs fournis.

## Structure

```
onboarding/
├── bindings/
│   └── onboarding_binding.dart
└── views/
    ├── splash_screen.dart
    └── onboarding_screen.dart
```

## Flux utilisateur

### 1. Splash Screen (`/splash`)

- Affiche le logo ProsArtisan avec shield et cœur
- Indicateur de chargement
- Badge "SECURE CONNECTION"
- Durée : 2 secondes
- Navigation automatique vers :
  - `/onboarding` si première utilisation
  - `/main` si utilisateur connecté
  - `/login` si onboarding complété mais non connecté

### 2. Onboarding Screen (`/onboarding`)

- 4 pages avec PageView
- Indicateurs de progression (dots)
- Boutons "Skip" et "Next"
- Compteur "Step X of 4"

#### Pages

**Page 1 : Find Verified Experts**

- Fond jaune (`#FDB750`)
- Illustration artisan
- Description : connexion avec artisans qualifiés

**Page 2 : Secure Escrow Payments**

- Fond sombre (`#424242`)
- Illustration cadenas/wallet
- Description : paiements sécurisés en séquestre

**Page 3 : Trust with ProsArtisan Score**

- Fond blanc
- Card profile avec :
  - Badge "VERIFIED"
  - Badge "ELITE PROVIDER"
  - Score 980/1000
  - Nom : Moussa Traoré
  - Métier : Master Carpenter • 8 yrs exp.
  - 5 étoiles
  - Stats : 99% Reliability, Top Quality Feedback
- Description : système de notation

**Page 4 : Ready to Begin?**

- Fond jaune (`#FDB750`)
- Illustration groupe d'artisans
- Description : rejoindre l'écosystème
- Bouton "Get Started" → `/login`

## Stockage

Le flag `onboarded` est sauvegardé dans `GetStorage` via `StorageService.setOnboarded(true)` à la fin de l'onboarding.

## Couleurs

- Primary Blue : `#5B5FEF`
- Yellow/Gold : `#FDB750`
- Dark Gray : `#424242`
- Text Dark : `#1A1A1A`
- Text Gray : `#616161`
- Text Light : `#9E9E9E`

## Assets requis

Les images suivantes doivent être ajoutées dans `assets/images/` :

- `onboarding_1.png` - Illustration artisan avec outils
- `onboarding_2.png` - Illustration cadenas/wallet doré
- `onboarding_3.png` - Photo profil artisan (optionnel, généré en code)
- `onboarding_4.png` - Illustration groupe artisans

Si les images ne sont pas présentes, des placeholders avec icônes sont affichés.

## Navigation

```dart
// Depuis n'importe où dans l'app
Get.toNamed(Routes.onboarding);

// Compléter l'onboarding
StorageService.setOnboarded(true);
Get.offAllNamed(Routes.login);
```

## Personnalisation

Pour modifier les pages d'onboarding, éditez la liste `_pages` dans `onboarding_screen.dart` :

```dart
final List<OnboardingPage> _pages = [
  OnboardingPage(
    image: 'assets/images/custom.png',
    title: 'Votre titre',
    description: 'Votre description',
    backgroundColor: const Color(0xFFCOLOR),
  ),
];
```
