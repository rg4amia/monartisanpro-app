import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    UserGroupIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from 'recharts';

export default function AnalyticsIndex({ analytics, period }) {
    const [selectedPeriod, setSelectedPeriod] = useState(period);

    const handlePeriodChange = (newPeriod) => {
        setSelectedPeriod(newPeriod);
        router.get('/backoffice/analytics', { period: newPeriod }, { preserveState: true });
    };

    const formatAmount = (centimes) => {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0,
        }).format(centimes / 100);
    };

    const formatNumber = (num) => {
        return new Intl.NumberFormat('fr-FR').format(num);
    };

    const getGrowthIcon = (current, previous) => {
        if (current > previous) return ArrowTrendingUpIcon;
        if (current < previous) return ArrowTrendingDownIcon;
        return ArrowPathIcon;
    };

    const getGrowthColor = (current, previous) => {
        if (current > previous) return 'text-green-600';
        if (current < previous) return 'text-red-600';
        return 'text-gray-600';
    };

    const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

    return (
        <BackofficeLayout>
            <Head title="Analytics" />

            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Analytics</h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Vue d'ensemble des performances de la plateforme
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <select
                            className="border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                            value={selectedPeriod}
                            onChange={(e) => handlePeriodChange(e.target.value)}
                        >
                            <option value="7">7 derniers jours</option>
                            <option value="30">30 derniers jours</option>
                            <option value="90">90 derniers jours</option>
                            <option value="365">1 an</option>
                        </select>
                        <Link href="/backoffice/analytics/revenue">
                            <button className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Revenus
                            </button>
                        </Link>
                        <Link href="/backoffice/analytics/users">
                            <button className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                Utilisateurs
                            </button>
                        </Link>
                        <Link href="/backoffice/analytics/performance">
                            <button className="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                                Performance
                            </button>
                        </Link>
                    </div>
                </div>
            </div>

            {/* Overview Stats */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <UserGroupIcon className="h-8 w-8 text-blue-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Utilisateurs Totaux
                                    </dt>
                                    <dd className="flex items-baseline">
                                        <div className="text-2xl font-semibold text-gray-900">
                                            {formatNumber(analytics.overview.totalUsers)}
                                        </div>
                                        <div className="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                            +{analytics.overview.newUsers} nouveaux
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <CurrencyDollarIcon className="h-8 w-8 text-green-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Volume Transactions
                                    </dt>
                                    <dd className="flex items-baseline">
                                        <div className="text-2xl font-semibold text-gray-900">
                                            {formatAmount(analytics.overview.totalVolume)}
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <ChartBarIcon className="h-8 w-8 text-purple-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Missions Actives
                                    </dt>
                                    <dd className="flex items-baseline">
                                        <div className="text-2xl font-semibold text-gray-900">
                                            {formatNumber(analytics.overview.activeMissions)}
                                        </div>
                                        <div className="ml-2 text-sm text-gray-500">
                                            / {formatNumber(analytics.overview.completedMissions)} terminées
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <ArrowTrendingUpIcon className="h-8 w-8 text-yellow-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Note Moyenne
                                    </dt>
                                    <dd className="flex items-baseline">
                                        <div className="text-2xl font-semibold text-gray-900">
                                            {analytics.overview.averageRating ? analytics.overview.averageRating.toFixed(1) : 'N/A'}
                                        </div>
                                        <div className="ml-2 text-sm text-gray-500">
                                            / 5.0
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                {/* User Growth Chart */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Croissance des Utilisateurs</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <LineChart data={analytics.userGrowth}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="date" />
                            <YAxis />
                            <Tooltip />
                            <Line type="monotone" dataKey="clients" stroke="#8884d8" name="Clients" />
                            <Line type="monotone" dataKey="artisans" stroke="#82ca9d" name="Artisans" />
                            <Line type="monotone" dataKey="fournisseurs" stroke="#ffc658" name="Fournisseurs" />
                        </LineChart>
                    </ResponsiveContainer>
                </div>

                {/* Transaction Trends */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Tendances des Transactions</h2>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={analytics.transactionTrends}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="date" />
                            <YAxis />
                            <Tooltip formatter={(value) => [formatAmount(value), 'Montant']} />
                            <Bar dataKey="volume" fill="#8884d8" name="Volume" />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                {/* Reputation Distribution */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Distribution des Scores</h2>
                    <ResponsiveContainer width="100%" height={250}>
                        <PieChart>
                            <Pie
                                data={[
                                    { name: 'Excellent (≥800)', value: analytics.reputationDistribution.excellent },
                                    { name: 'Bon (600-799)', value: analytics.reputationDistribution.good },
                                    { name: 'Moyen (400-599)', value: analytics.reputationDistribution.average },
                                    { name: 'Faible (<400)', value: analytics.reputationDistribution.poor },
                                ]}
                                cx="50%"
                                cy="50%"
                                labelLine={false}
                                label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                outerRadius={80}
                                fill="#8884d8"
                                dataKey="value"
                            >
                                {[
                                    { name: 'Excellent (≥800)', value: analytics.reputationDistribution.excellent },
                                    { name: 'Bon (600-799)', value: analytics.reputationDistribution.good },
                                    { name: 'Moyen (400-599)', value: analytics.reputationDistribution.average },
                                    { name: 'Faible (<400)', value: analytics.reputationDistribution.poor },
                                ].map((entry, index) => (
                                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Mission Stats */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Statistiques des Missions</h2>
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600">Total missions</span>
                            <span className="text-sm font-medium text-gray-900">
                                {formatNumber(analytics.missionStats.totalMissions)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600">Nouvelles missions</span>
                            <span className="text-sm font-medium text-gray-900">
                                {formatNumber(analytics.missionStats.newMissions)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600">Valeur moyenne</span>
                            <span className="text-sm font-medium text-gray-900">
                                {formatAmount(analytics.missionStats.averageMissionValue)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-gray-600">Taux de completion</span>
                            <span className="text-sm font-medium text-gray-900">
                                {analytics.missionStats.missionCompletionRate.toFixed(1)}%
                            </span>
                        </div>
                    </div>
                </div>

                {/* Geographic Data */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Répartition Géographique</h2>
                    <div className="space-y-3">
                        {analytics.geographicData.slice(0, 5).map((zone, index) => (
                            <div key={index} className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">{zone.zone}</span>
                                <span className="text-sm font-medium text-gray-900">
                                    {zone.artisan_count} artisans
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Top Artisans */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Top Artisans</h2>
                    <div className="space-y-4">
                        {analytics.topArtisans.slice(0, 5).map((artisan, index) => (
                            <div key={index} className="flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-medium text-gray-900">{artisan.email}</div>
                                    <div className="text-xs text-gray-500">
                                        Score: {artisan.current_score} • {artisan.completed_projects} projets
                                    </div>
                                </div>
                                <div className="text-sm font-medium text-gray-900">
                                    {formatAmount(artisan.total_earnings)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Top Clients */}
                <div className="bg-white shadow rounded-lg p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">Top Clients</h2>
                    <div className="space-y-4">
                        {analytics.topClients.slice(0, 5).map((client, index) => (
                            <div key={index} className="flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-medium text-gray-900">{client.email}</div>
                                    <div className="text-xs text-gray-500">
                                        {client.missions_created} missions créées
                                    </div>
                                </div>
                                <div className="text-sm font-medium text-gray-900">
                                    {formatAmount(client.total_spent)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </BackofficeLayout>
    );
}
