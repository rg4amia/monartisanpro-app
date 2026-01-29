#!/bin/bash

# ============================================================================
# PROSARTISAN - STRUCTURE DES PROJETS
# Frontend: Flutter + GetX + Hive
# Backend: Laravel + React + Inertia.js + Redis
# ============================================================================

echo "🚀 Création de la structure ProsArtisan..."

# ============================================================================
# 1. PROJET FRONTEND MOBILE (FLUTTER)
# ============================================================================

echo "📱 Création du projet Flutter..."

# Créer le projet Flutter
flutter create prosartisan_mobile
cd prosartisan_mobile

# Structure des dossiers
mkdir -p lib/{core,features,shared}
mkdir -p lib/core/{config,constants,routes,services,utils,middleware}
mkdir -p lib/core/services/{api,storage,location,notification,payment}
mkdir -p lib/shared/{widgets,models,controllers,bindings}

# Features (Bounded Contexts)
mkdir -p lib/features/auth/{data,domain,presentation}
mkdir -p lib/features/auth/presentation/{controllers,pages,widgets}
mkdir -p lib/features/auth/data/{models,repositories,datasources}
mkdir -p lib/features/auth/domain/{entities,repositories,usecases}

mkdir -p lib/features/marketplace/{data,domain,presentation}
mkdir -p lib/features/marketplace/presentation/{controllers,pages,widgets}
mkdir -p lib/features/marketplace/data/{models,repositories,datasources}
mkdir -p lib/features/marketplace/domain/{entities,repositories,usecases}

mkdir -p lib/features/mission/{data,domain,presentation}
mkdir -p lib/features/mission/presentation/{controllers,pages,widgets}

mkdir -p lib/features/payment/{data,domain,presentation}
mkdir -p lib/features/payment/presentation/{controllers,pages,widgets}

mkdir -p lib/features/worksite/{data,domain,presentation}
mkdir -p lib/features/worksite/presentation/{controllers,pages,widgets}

mkdir -p lib/features/reputation/{data,domain,presentation}
mkdir -p lib/features/reputation/presentation/{controllers,pages,widgets}

mkdir -p lib/features/profile/{data,domain,presentation}
mkdir -p lib/features/profile/presentation/{controllers,pages,widgets}

# Assets
mkdir -p assets/{images,icons,fonts,lottie}
mkdir -p assets/images/{logos,onboarding,categories}

# Tests
mkdir -p test/{unit,widget,integration}

echo "✅ Structure Flutter créée"

# ============================================================================
# 2. PROJET BACKEND (LARAVEL + INERTIA + REACT)
# ============================================================================

cd ..
echo "🔧 Création du projet Laravel..."

# Créer le projet Laravel
composer create-project laravel/laravel prosartisan_backend
cd prosartisan_backend

# Installer Inertia.js
composer require inertiajs/inertia-laravel
php artisan inertia:middleware

# Installer Redis et autres dépendances
composer require predis/predis
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require intervention/image
composer require laravel/cashier

# Structure Backend Laravel (DDD)
mkdir -p app/Domain
mkdir -p app/Domain/{Identity,Marketplace,Financial,Worksite,Reputation,Dispute}

# Identity Context
mkdir -p app/Domain/Identity/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Identity/Models/{Client,Artisan,Fournisseur,ReferentZone}

# Marketplace Context
mkdir -p app/Domain/Marketplace/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Marketplace/Models/{Mission,Devis,Categorie}

# Financial Context
mkdir -p app/Domain/Financial/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Financial/Models/{Sequestre,JetonMateriel,Transaction}

# Worksite Context
mkdir -p app/Domain/Worksite/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Worksite/Models/{Chantier,Jalon,PreuveLivraison}

# Reputation Context
mkdir -p app/Domain/Reputation/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Reputation/Models/{ScoreNZassa,Evaluation}

# Dispute Context
mkdir -p app/Domain/Dispute/{Models,Repositories,Services,Events,DTOs}
mkdir -p app/Domain/Dispute/Models/{Litige,Mediation,Arbitrage}

# Application Layer
mkdir -p app/Application
mkdir -p app/Application/{UseCases,DTOs,Handlers}
mkdir -p app/Application/UseCases/{Auth,Mission,Payment,Worksite,Reputation}

# Infrastructure
mkdir -p app/Infrastructure
mkdir -p app/Infrastructure/{Repositories,Services,Providers}
mkdir -p app/Infrastructure/Services/{MobileMoney,GPS,SMS,WhatsApp,Firebase}

# API Controllers
mkdir -p app/Http/Controllers/Api/V1
mkdir -p app/Http/Controllers/Api/V1/{Auth,Artisan,Client,Mission,Payment,Worksite}

# Inertia Controllers (Back-office)
mkdir -p app/Http/Controllers/Backoffice
mkdir -p app/Http/Controllers/Backoffice/{Dashboard,KYC,Transaction,Dispute,Analytics}

# Middleware
mkdir -p app/Http/Middleware/{Auth,Role,KYC}

# Resources & Requests
mkdir -p app/Http/Resources/{User,Mission,Payment,Worksite}
mkdir -p app/Http/Requests/{Auth,Mission,Payment}

# Database
mkdir -p database/migrations/{identity,marketplace,financial,worksite,reputation,dispute}
mkdir -p database/seeders
mkdir -p database/factories

# Tests
mkdir -p tests/Unit/{Domain,Application,Infrastructure}
mkdir -p tests/Feature/{Auth,Mission,Payment,Worksite}

# ============================================================================
# 3. FRONTEND BACKOFFICE (REACT + INERTIA)
# ============================================================================

echo "⚛️ Configuration React + Inertia..."

# Installer les dépendances Node.js
npm install @inertiajs/react react react-dom
npm install -D @vitejs/plugin-react
npm install axios
npm install react-router-dom
npm install @headlessui/react @heroicons/react
npm install recharts
npm install react-hot-toast
npm install date-fns
npm install react-hook-form
npm install @tanstack/react-query
npm install zustand

# Structure React
mkdir -p resources/js
mkdir -p resources/js/{Components,Pages,Layouts,Hooks,Utils,Stores}

# Layouts
mkdir -p resources/js/Layouts/{Auth,Backoffice,Public}

# Pages Backoffice
mkdir -p resources/js/Pages/Backoffice
mkdir -p resources/js/Pages/Backoffice/{Dashboard,KYC,Transactions,Litiges,Analytics,Users}
mkdir -p resources/js/Pages/Backoffice/KYC/{Pending,Approved,Rejected}
mkdir -p resources/js/Pages/Backoffice/Transactions/{Sequestre,Jetons,MobileMoney}
mkdir -p resources/js/Pages/Backoffice/Litiges/{Active,Resolved,Archived}

# Components
mkdir -p resources/js/Components/Common
mkdir -p resources/js/Components/Backoffice
mkdir -p resources/js/Components/Common/{Button,Input,Modal,Table,Card,Badge}
mkdir -p resources/js/Components/Backoffice/{Sidebar,Navbar,Stats,Charts,Forms}

# Hooks personnalisés
mkdir -p resources/js/Hooks/{useAuth,useNotification,usePermission}

# Stores (State Management)
mkdir -p resources/js/Stores/{auth,notification,ui}

# Utils
mkdir -p resources/js/Utils/{api,validation,formatting,constants}

# Public assets
mkdir -p public/images/{logos,avatars,documents}
mkdir -p public/uploads/{kyc,preuves,signatures}

echo "✅ Structure Laravel + React créée"

# ============================================================================
# 4. CONFIGURATION REDIS
# ============================================================================

echo "🔴 Configuration Redis..."

# Créer les fichiers de configuration Redis
mkdir -p storage/redis

# ============================================================================
# 5. DOCUMENTATION
# ============================================================================

cd ..
mkdir -p docs/{api,architecture,deployment,user_guides}
mkdir -p docs/api/{mobile,backoffice}
mkdir -p docs/architecture/{ddd,database,infrastructure}

echo "📚 Structure de documentation créée"

# ============================================================================
# RÉSUMÉ DE LA STRUCTURE
# ============================================================================

echo ""
echo "✨ ================================"
echo "✨ STRUCTURE PROSARTISAN CRÉÉE"
echo "✨ ================================"
echo ""
echo "📱 Frontend Mobile:"
echo "   └── prosartisan_mobile/ (Flutter + GetX + Hive)"
echo ""
echo "🔧 Backend:"
echo "   └── prosartisan_backend/ (Laravel + Inertia + React + Redis)"
echo ""
echo "📂 Structure DDD complète avec:"
echo "   ✓ 6 Bounded Contexts"
echo "   ✓ Clean Architecture"
echo "   ✓ API REST pour Mobile"
echo "   ✓ Back-office Inertia + React"
echo ""
echo "🚀 Prochaines étapes:"
echo "   1. Configuration des .env"
echo "   2. Migration de la base de données"
echo "   3. Configuration Firebase"
echo "   4. Configuration Mobile Money"
echo ""