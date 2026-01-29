import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, router } from '@inertiajs/react';
import {
 ChartBarIcon,
 ArrowTrendingUpIcon,
 ExclamationTriangleIcon,
 ClockIcon
} from '@heroicons/react/24/outline';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line } from 'recharts';

export default function AnalyticsPerformance({ performanceData, period }) {
 const [selectedPeriod, setSelectedPeriod] = useState(period);

 const handlePeriodChange = (newPeriod) => {
  setSelectedPeriod(newPeriod);
  router.get('/backoffice/analytics/performance', { period: newPeriod }, { preserveState: true });
 };

 const formatNumber = (num) => {
  return new Intl.NumberFormat('fr-FR').format(num);
 };

 const formatAmount = (centimes) => {
  return new Intl.NumberFormat('fr-FR', {
   style: 'currency',
   currency: 'XOF',
   minimumFractionDigits: 0,
  }).format(centimes / 100);
 };

 return (
  <BackofficeLayout>
   <Head title="Analytics - Performance" />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div>
      <h1 className="text-3xl font-bold text-gray-900">Analytics - Performance</h1>
      <p className="mt-2 text-sm text-gray-700">
       Analyse des performances et métriques clés de la plateforme
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
     </div>
    </div>
   </div>

   {/* Performance Metrics Cards */}
   <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div className="bg-white overflow-hidden shadow rounded-lg">
     <div className="p-5">
      <div className="flex items-center">
       <div className="flex-shrink-0">
        <ChartBarIcon className="h-8 w-8 text-blue-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Missions Totales
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatNumber(performanceData.platformMetrics.totalMissions)}
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
        <ArrowTrendingUpIcon className="h-8 w-8 text-green-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Taux de Conversion
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {performanceData.conversionRates.quoteToAcceptance.toFixed(1)}%
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
        <ClockIcon className="h-8 w-8 text-purple-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Temps Moyen Completion
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {performanceData.platformMetrics.averageCompletionTime}j
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
        <ExclamationTriangleIcon className="h-8 w-8 text-red-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Taux de Litiges
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {performanceData.disputeMetrics.disputeRate.toFixed(1)}%
         </dd>
        </dl>
       </div>
      </div>
     </div>
    </div>
   </div>

   {/* Conversion Funnel */}
   <div className="bg-white shadow rounded-lg p-6 mb-8">
    <h2 className="text-lg font-medium text-gray-900 mb-4">Entonnoir de Conversion</h2>
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
     <div className="text-center">
      <div className="bg-blue-100 rounded-lg p-6">
       <div className="text-3xl font-bold text-blue-600 mb-2">
        100%
       </div>
       <div className="text-sm text-gray-600">
        Missions Créées
       </div>
      </div>
     </div>
     <div className="text-center">
      <div className="bg-yellow-100 rounded-lg p-6">
       <div className="text-3xl font-bold text-yellow-600 mb-2">
        {performanceData.conversionRates.missionToQuote.toFixed(1)}%
       </div>
       <div className="text-sm text-gray-600">
        Missions avec Devis
       </div>
      </div>
     </div>
     <div className="text-center">
      <div className="bg-green-100 rounded-lg p-6">
       <div className="text-3xl font-bold text-green-600 mb-2">
        {performanceData.conversionRates.quoteToAcceptance.toFixed(1)}%
       </div>
       <div className="text-sm text-gray-600">
        Devis Acceptés
       </div>
      </div>
     </div>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    {/* Average Values */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Valeurs Moyennes</h2>
     <div className="space-y-4">
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Valeur moyenne mission</span>
       <span className="text-sm font-medium text-gray-900">
        {formatAmount(performanceData.averageValues.averageMissionValue)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Valeur moyenne transaction</span>
       <span className="text-sm font-medium text-gray-900">
        {formatAmount(performanceData.averageValues.averageTransactionValue)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Note moyenne</span>
       <span className="text-sm font-medium text-gray-900">
        {performanceData.averageValues.averageRating ? performanceData.averageValues.averageRating.toFixed(1) : 'N/A'} / 5.0
       </span>
      </div>
     </div>
    </div>

    {/* Completion Rates */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Taux de Completion</h2>
     <div className="space-y-4">
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Chantiers terminés</span>
       <span className="text-sm font-medium text-gray-900">
        {performanceData.completionRates.chantiersCompletionRate.toFixed(1)}%
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Missions terminées</span>
       <span className="text-sm font-medium text-gray-900">
        {formatNumber(performanceData.platformMetrics.completedMissions)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Volume total transactions</span>
       <span className="text-sm font-medium text-gray-900">
        {formatAmount(performanceData.platformMetrics.totalTransactionVolume)}
       </span>
      </div>
     </div>
    </div>
   </div>

   {/* Dispute Metrics */}
   <div className="bg-white shadow rounded-lg p-6">
    <h2 className="text-lg font-medium text-gray-900 mb-4">Métriques des Litiges</h2>
    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
     <div className="text-center">
      <div className="text-3xl font-bold text-red-600">
       {formatNumber(performanceData.disputeMetrics.totalDisputes)}
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Total Litiges
      </div>
     </div>
     <div className="text-center">
      <div className="text-3xl font-bold text-yellow-600">
       {performanceData.disputeMetrics.disputeRate.toFixed(1)}%
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Taux de Litiges
      </div>
     </div>
     <div className="text-center">
      <div className="text-3xl font-bold text-green-600">
       {formatNumber(performanceData.disputeMetrics.resolvedDisputes)}
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Litiges Résolus
      </div>
     </div>
     <div className="text-center">
      <div className="text-3xl font-bold text-blue-600">
       {performanceData.disputeMetrics.averageResolutionTime}j
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Temps Moyen Résolution
      </div>
     </div>
    </div>
   </div>
  </BackofficeLayout>
 );
}
