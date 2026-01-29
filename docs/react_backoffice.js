// ============================================================================
// package.json - Dépendances React
// ============================================================================

/*
{
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build"
  },
  "devDependencies": {
    "@inertiajs/react": "^1.0.0",
    "@tailwindcss/forms": "^0.5.7",
    "@vitejs/plugin-react": "^4.2.1",
    "autoprefixer": "^10.4.16",
    "axios": "^1.6.4",
    "laravel-vite-plugin": "^1.0.0",
    "postcss": "^8.4.32",
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "tailwindcss": "^3.4.0",
    "vite": "^5.0.0"
  },
  "dependencies": {
    "@headlessui/react": "^1.7.17",
    "@heroicons/react": "^2.1.1",
    "recharts": "^2.10.3",
    "react-hot-toast": "^2.4.1",
    "date-fns": "^3.0.6",
    "react-hook-form": "^7.49.3",
    "@tanstack/react-query": "^5.17.15",
    "zustand": "^4.4.7"
  }
}
*/

// ============================================================================
// vite.config.js
// ============================================================================

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});

// ============================================================================
// resources/js/app.jsx - Point d'entrée React
// ============================================================================

import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

const appName = import.meta.env.VITE_APP_NAME || 'ProsArtisan';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.jsx`,
        import.meta.glob('./Pages/**/*.jsx')
    ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#1E88E5',
    },
});

// ============================================================================
// resources/js/Layouts/Backoffice/BackofficeLayout.jsx
// ============================================================================

import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { 
    HomeIcon, 
    UserGroupIcon, 
    CurrencyDollarIcon,
    ChartBarIcon,
    ExclamationTriangleIcon,
    CogIcon,
    Bars3Icon,
    XMarkIcon
} from '@heroicons/react/24/outline';

export default function BackofficeLayout({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { auth } = usePage().props;

    const navigation = [
        { name: 'Dashboard', href: '/backoffice/dashboard', icon: HomeIcon },
        { name: 'KYC & Utilisateurs', href: '/backoffice/kyc', icon: UserGroupIcon },
        { name: 'Transactions', href: '/backoffice/transactions', icon: CurrencyDollarIcon },
        { name: 'Litiges', href: '/backoffice/litiges', icon: ExclamationTriangleIcon },
        { name: 'Analytics', href: '/backoffice/analytics', icon: ChartBarIcon },
        { name: 'Paramètres', href: '/backoffice/settings', icon: CogIcon },
    ];

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Sidebar Mobile */}
            <div className={`fixed inset-0 z-40 lg:hidden ${sidebarOpen ? '' : 'hidden'}`}>
                <div className="fixed inset-0 bg-gray-600 bg-opacity-75" onClick={() => setSidebarOpen(false)} />
                <div className="fixed inset-y-0 left-0 flex w-64 flex-col bg-white">
                    <div className="flex items-center justify-between px-4 py-5">
                        <span className="text-xl font-bold text-blue-600">ProsArtisan</span>
                        <button onClick={() => setSidebarOpen(false)}>
                            <XMarkIcon className="h-6 w-6" />
                        </button>
                    </div>
                    <nav className="flex-1 space-y-1 px-2 py-4">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-100"
                            >
                                <item.icon className="mr-3 h-6 w-6" />
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Sidebar Desktop */}
            <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
                <div className="flex flex-col flex-grow border-r border-gray-200 bg-white overflow-y-auto">
                    <div className="flex items-center flex-shrink-0 px-4 py-5">
                        <span className="text-2xl font-bold text-blue-600">ProsArtisan</span>
                    </div>
                    <nav className="flex-1 space-y-1 px-2 py-4">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-100 transition-colors"
                            >
                                <item.icon className="mr-3 h-6 w-6 text-gray-500" />
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Main Content */}
            <div className="lg:pl-64 flex flex-col flex-1">
                {/* Top Bar */}
                <div className="sticky top-0 z-10 flex h-16 flex-shrink-0 bg-white shadow">
                    <button
                        type="button"
                        className="px-4 text-gray-500 focus:outline-none lg:hidden"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Bars3Icon className="h-6 w-6" />
                    </button>
                    <div className="flex flex-1 justify-between px-4">
                        <div className="flex flex-1" />
                        <div className="ml-4 flex items-center md:ml-6">
                            <span className="text-sm text-gray-700">{auth.user.nom}</span>
                        </div>
                    </div>
                </div>

                {/* Page Content */}
                <main className="flex-1">
                    <div className="py-6">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}

// ============================================================================
// resources/js/Pages/Backoffice/Dashboard/Index.jsx
// ============================================================================

import React from 'react';
import BackofficeLayout from '@/Layouts/Backoffice/BackofficeLayout';
import { Head } from '@inertiajs/react';
import {
    UserGroupIcon,
    CurrencyDollarIcon,
    CheckCircleIcon,
    ClockIcon
} from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function Dashboard({ stats, chartData }) {
    const cards = [
        {
            name: 'Artisans Actifs',
            value: stats.artisans_actifs,
            icon: UserGroupIcon,
            color: 'bg-blue-500',
        },
        {
            name: 'Missions en Cours',
            value: stats.missions_en_cours,
            icon: ClockIcon,
            color: 'bg-yellow-500',
        },
        {
            name: 'Transactions Aujourd\'hui',
            value: `${stats.transactions_aujourd_hui} FCFA`,
            icon: CurrencyDollarIcon,
            color: 'bg-green-500',
        },
        {
            name: 'Chantiers Terminés',
            value: stats.chantiers_termines,
            icon: CheckCircleIcon,
            color: 'bg-purple-500',
        },
    ];

    return (
        <BackofficeLayout>
            <Head title="Dashboard" />

            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
                <p className="mt-2 text-sm text-gray-700">Vue d'ensemble de la plateforme ProsArtisan</p>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                {cards.map((card) => (
                    <div key={card.name} className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className={`flex-shrink-0 rounded-md p-3 ${card.color}`}>
                                    <card.icon className="h-6 w-6 text-white" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">
                                            {card.name}
                                        </dt>
                                        <dd className="text-lg font-semibold text-gray-900">
                                            {card.value}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Chart */}
            <div className="bg-white shadow rounded-lg p-6">
                <h2 className="text-lg font-medium text-gray-900 mb-4">Transactions des 7 derniers jours</h2>
                <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={chartData}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="date" />
                        <YAxis />
                        <Tooltip />
                        <Line type="monotone" dataKey="montant" stroke="#1E88E5" strokeWidth={2} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </BackofficeLayout>
    );
}

// ============================================================================
// resources/js/Pages/Backoffice/KYC/Pending.jsx
// ============================================================================

import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/Backoffice/BackofficeLayout';
import { Head, router } from '@inertiajs/react';
import { CheckIcon, XMarkIcon } from '@heroicons/react/24/outline';
import toast from 'react-hot-toast';

export default function PendingKYC({ artisans }) {
    const [selectedArtisan, setSelectedArtisan] = useState(null);

    const handleApprove = (artisanId) => {
        if (confirm('Êtes-vous sûr de vouloir approuver ce profil ?')) {
            router.post(`/backoffice/kyc/${artisanId}/approve`, {}, {
                onSuccess: () => {
                    toast.success('Profil approuvé avec succès');
                    setSelectedArtisan(null);
                },
                onError: () => toast.error('Erreur lors de l\'approbation'),
            });
        }
    };

    const handleReject = (artisanId) => {
        const raison = prompt('Raison du rejet :');
        if (raison) {
            router.post(`/backoffice/kyc/${artisanId}/reject`, { raison }, {
                onSuccess: () => {
                    toast.success('Profil rejeté');
                    setSelectedArtisan(null);
                },
                onError: () => toast.error('Erreur lors du rejet'),
            });
        }
    };

    return (
        <BackofficeLayout>
            <Head title="KYC en attente" />

            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900">Vérifications KYC en Attente</h1>
                <p className="mt-2 text-sm text-gray-700">
                    {artisans.length} artisan(s) en attente de validation
                </p>
            </div>

            <div className="bg-white shadow overflow-hidden rounded-lg">
                <ul className="divide-y divide-gray-200">
                    {artisans.map((artisan) => (
                        <li key={artisan.id} className="p-6 hover:bg-gray-50 cursor-pointer"
                            onClick={() => setSelectedArtisan(artisan)}>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center">
                                    <img
                                        src={artisan.avatar || '/images/default-avatar.png'}
                                        alt={artisan.nom}
                                        className="h-12 w-12 rounded-full"
                                    />
                                    <div className="ml-4">
                                        <h3 className="text-lg font-medium text-gray-900">
                                            {artisan.nom} {artisan.prenoms}
                                        </h3>
                                        <p className="text-sm text-gray-500">
                                            {artisan.categorie?.nom} - {artisan.commune}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex space-x-2">
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            handleApprove(artisan.id);
                                        }}
                                        className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700"
                                    >
                                        <CheckIcon className="h-5 w-5 mr-1" />
                                        Approuver
                                    </button>
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            handleReject(artisan.id);
                                        }}
                                        className="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700"
                                    >
                                        <XMarkIcon className="h-5 w-5 mr-1" />
                                        Rejeter
                                    </button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

            {/* Modal de détails */}
            {selectedArtisan && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center"
                     onClick={() => setSelectedArtisan(null)}>
                    <div className="bg-white rounded-lg p-8 max-w-2xl w-full" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-2xl font-bold mb-4">Détails du profil</h2>
                        <div className="space-y-4">
                            <div>
                                <h3 className="font-semibold">Pièce d'identité</h3>
                                <img 
                                    src={selectedArtisan.kyc_documents?.cni} 
                                    alt="CNI" 
                                    className="mt-2 max-w-full h-auto"
                                />
                            </div>
                            <div>
                                <h3 className="font-semibold">Selfie</h3>
                                <img 
                                    src={selectedArtisan.kyc_documents?.selfie} 
                                    alt="Selfie" 
                                    className="mt-2 max-w-full h-auto"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </BackofficeLayout>
    );
}

// ============================================================================
// resources/js/Components/Common/Button.jsx
// ============================================================================

import React from 'react';

export default function Button({ 
    children, 
    variant = 'primary', 
    size = 'md',
    className = '', 
    ...props 
}) {
    const baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors';
    
    const variants = {
        primary: 'bg-blue-600 text-white hover:bg-blue-700',
        secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        success: 'bg-green-600 text-white hover:bg-green-700',
    };

    const sizes = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-base',
        lg: 'px-6 py-3 text-lg',
    };

    return (
        <button
            className={`${baseClasses} ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}