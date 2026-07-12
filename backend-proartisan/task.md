# Checklist d'implémentation — Espace Fournisseur & Gestion des Rôles

- [x] **1. Espace Fournisseur (Backoffice & API)**
  - [x] Middleware `SupplierOnly` (`app/Http/Middleware/SupplierOnly.php`)
  - [x] Contrôleur `SupplierBackofficeController` (`app/Http/Controllers/Supplier/SupplierBackofficeController.php`)
  - [x] Contrôleur `SupplierDashboardController` (`app/Http/Controllers/Api/V1/Supplier/SupplierDashboardController.php`)
  - [x] Enregistrement et configuration du middleware dans `bootstrap/app.php`
  - [x] Redirection de connexion pour le rôle fournisseur dans `AuthenticatedSessionController.php`
  - [x] Déclaration des routes backoffice dans `routes/web.php`
  - [x] Déclaration des routes API dans `routes/api.php`
  - [x] Création de la console Inertia React (`resources/js/Pages/supplier/console.tsx`)
  - [x] Pages de redirection d'onglets (`dashboard.tsx`, `catalog.tsx`, `orders.tsx`, `litiges.tsx` sous `Pages/supplier/`)

- [x] **2. Gestion des Rôles & Actions (Permissions)**
  - [x] Migration des tables `permissions` et `permission_role`
  - [x] Seeder `PermissionSeeder.php`
  - [x] Trait `HasPermissions` (`app/Traits/HasPermissions.php`)
  - [x] Intégration du trait dans le modèle `User` (`app/Models/User.php`)
  - [x] Service `RolePermissionService` (`app/Services/RolePermissionService.php`)
  - [x] Form Requests pour l'administration des permissions
  - [x] Contrôleur `AdminRolePermissionController` (`app/Http/Controllers/Api/V1/AdminRolePermissionController.php`)
  - [x] Enregistrement des routes API d'administration des permissions dans `routes/api.php`
  - [x] Liaison au moteur d'autorisation Laravel via `Gate::before` dans `AppServiceProvider.php`
  - [x] Sécurisation des routes critiques de l'API avec le middleware `can`

- [x] **3. Vérification & Tests**
  - [x] Test fonctionnel de l'espace fournisseur (`tests/Feature/SupplierBackofficeTest.php`)
  - [x] Test fonctionnel de la gestion des permissions (`tests/Feature/RolePermissionTest.php`)
  - [x] Exécution et validation de la suite de tests
