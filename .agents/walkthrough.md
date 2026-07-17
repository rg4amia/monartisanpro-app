# Rapport de Walkthrough — E-Commerce, Livraison & Calculs TTC

Toutes les modifications concernant la tarification TTC avec majoration, le reversement des commissions plateforme au déblocage du séquestre, le workflow de notifications client/fournisseur/livreur, et l'intégration des vues de commande e-commerce Flutter ont été finalisées et validées avec succès.

---

## 1. Modifications Backend (Laravel)

### Tarification TTC & Calculs de Commissions

- **[Devis.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Models/Devis.php)** : Les accesseurs (`montant_total`, `montant_materiaux`, `montant_mo`) ont été adaptés pour retourner les montants en **TTC** en appliquant les ratios de commission depuis la table `settings` (`platform_fee_ratio` = 3% sur les matériaux, `commission_service` = 10% sur la main d'œuvre).
- **[DevisService.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Services/DevisService.php)** : Lors de l'acceptation du devis, les jalons stockés en base sont automatiquement convertis en TTC. Le fractionnement du séquestre se base sur les valeurs TTC du devis pour éviter les écarts.
- **[WalletService.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Services/WalletService.php)** :
  - Ajout de la méthode `creditPlatformFinancialAccount()` qui verse les commissions de la plateforme sur le wallet principal de l'administrateur système (l'admin).
  - Dans `releaseJalon()`, la commission de main d'œuvre est extraite du montant TTC du jalon : l'artisan reçoit sa part nette HT sur son mobile money et la plateforme reçoit le montant de sa commission.
- **[JCodeService.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Services/JCodeService.php)** : Dans `settleSupplierPayment()`, l'artisan est débité du montant TTC de la commande matériaux. Le fournisseur reçoit le montant HT moins sa commission vendeur (5%), et la plateforme ProsArtisan reçoit la somme des commissions cumulées (majoration 3% + retenue quincaillerie 5%).
- **[OrderService.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Services/OrderService.php)** :
  - Au déblocage des commandes e-commerce (`releaseSupplierFunds` et `releaseDriverFunds`), le gain net est versé au fournisseur / livreur et la commission plateforme est transférée sur le compte ProsArtisan.

### Notifications & Logistique Géolocalisée

- **[OrderService.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Services/OrderService.php)** :
  - Intégration de notifications SMS/Push via `NotificationService` à chaque étape clé (confirmation de paiement client/fournisseur, commande prête pour retrait, livreur en route, colis récupéré, livraison finalisée).
  - Ajout de `notifyDriversInArea()` : recherche de livreurs disponibles dans un rayon de 10 km autour du client et du fournisseur (via `ST_Distance_Sphere`). En cas de résultat vide, extension automatique de la recherche à tous les livreurs de la plateforme.
- **[DeliveryController.php](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/backend-proartisan/app/Http/Controllers/Api/V1/DeliveryController.php)** : Implémentation du filtre géographique progressif (rayon 10 km local puis extension globale) pour la liste des courses disponibles pour les livreurs (`available()`).

---

## 2. Modifications Frontend (Flutter)

### Vues & Expérience Utilisateur Premium

- **[client_suppliers_list_screen.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/modules/orders/views/client_suppliers_list_screen.dart)** : Liste complète des quincailleries partenaires agréées avec barre de recherche filtrante réactive et design premium.
- **[client_catalog_screen.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/modules/orders/views/client_catalog_screen.dart)** : Catalogue dynamique des articles d'un fournisseur avec calcul en temps réel des prix en TTC (+3% frais plateforme) et gestion de panier (ajouter/retirer, indicateurs de stock, barre de commande inférieure).
- **[order_checkout_screen.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/modules/orders/views/order_checkout_screen.dart)** : Écran de facturation détaillé listant les articles commandés, calculant les frais de livraison dynamique en fonction du véhicule et de la majoration (surge), affichant les frais plateforme et le montant total TTC.
- **[order_controller.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/modules/orders/controllers/order_controller.dart)** : Contrôleur gérant le chargement des fournisseurs/articles et l'état réactif global du panier.
- **[client_home_screen.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/modules/home/views/client_home_screen.dart)** : Ajout d'un bouton "Voir tout" et gestion de clics sur les fiches fournisseurs pour rediriger immédiatement vers le catalogue.
- **[app_routes.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/app/routes/app_routes.dart)** & **[app_pages.dart](file:///c:/Users/Utilisateur/Documents/GitHub/monartisanpro-app/frontend_flutter/lib/app/routes/app_pages.dart)** : Déclaration et routage des nouvelles vues.

---

## 3. Résultats de Validation

### Tests Automatisés Backend

- Tous les tests de paiement et de workflow passent à 100% :
  - `php artisan test --filter=DevisPaymentFlowTest` : **OK** (35 assertions)
  - `php artisan test --filter=OrderWorkflowTest` : **OK** (28 assertions)
  - `php artisan test --filter=FullMissionWorkflowTest` : **OK** (40 assertions)
