import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, router } from '@inertiajs/react';
import {
 UserGroupIcon,
 UserPlusIcon,
 CheckCircleIcon,
 ClockIcon
} from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from 'recharts';

export default function AnalyticsUsers({ userData, period }) {
 const [selectedPeriod, setSelectedPeriod] = useState(period);

 const handlePeriodChange = (newPeriod) => {
  setSelectedPeriod(newPeriod);
  router.get('/backoffice/analytics/users', { period: newPeriod }, { preserveState: true });
 };

 const formatNumber = (num) => {
  return new Intl.NumberFormat('fr-FR').format(num);
 };

 const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

 return (
  <BackofficeLayout>
   <Head title="Analytics - Utilisateurs" />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div>
      <h1 className="text-3xl font-bold text-gray-900">Analytics - Utilisateurs</h1>
      <p className="mt-2 text-sm text-gray-700">
       Analyse détaillée des utilisateurs de la plateforme
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

   {/* User Stats Cards */}
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
         <dd className="text-2xl font-semibold text-gray-900">
          {formatNumber(userData.userStats.totalUsers)}
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
        <UserPlusIcon className="h-8 w-8 text-green-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Nouveaux Utilisateurs
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatNumber(userData.userStats.newUsers)}
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
        <CheckCircleIcon className="h-8 w-8 text-purple-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Utilisateurs Actifs
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatNumber(userData.userStats.activeUsers)}
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
        <ClockIcon className="h-8 w-8 text-yellow-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Taux de Rétention
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {userData.userRetention.toFixed(1)}%
         </dd>
        </dl>
       </div>
      </div>
     </div>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    {/* User Segmentation by Type */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Répartition par Type d'Utilisateur</h2>
     <ResponsiveContainer width="100%" height={300}>
      <PieChart>
       <Pie
        data={userData.userSegmentation.byType}
        cx="50%"
        cy="50%"
        labelLine={false}
        label={({ user_type, percent }) => `${user_type} ${(percent * 100).toFixed(0)}%`}
        outerRadius={80}
        fill="#8884d8"
        dataKey="count"
       >
        {userData.userSegmentation.byType.map((entry, index) => (
         <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
        ))}
       </Pie>
       <Tooltip />
      </PieChart>
     </ResponsiveContainer>
    </div>

    {/* User Activity */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Activité des Utilisateurs</h2>
     <ResponsiveContainer width="100%" height={300}>
      <BarChart data={userData.userActivity}>
       <CartesianGrid strokeDasharray="3 3" />
       <XAxis dataKey="action" />
       <YAxis />
       <Tooltip />
       <Bar dataKey="count" fill="#3b82f6" />
      </BarChart>
     </ResponsiveContainer>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    {/* KYC Statistics */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Statistiques KYC</h2>
     <div className="space-y-4">
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Total soumissions</span>
       <span className="text-sm font-medium text-gray-900">
        {formatNumber(userData.kycStats.totalSubmissions)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Nouvelles soumissions</span>
       <span className="text-sm font-medium text-gray-900">
        {formatNumber(userData.kycStats.newSubmissions)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Approuvées</span>
       <span className="text-sm font-medium text-green-600">
        {formatNumber(userData.kycStats.approved)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">Rejetées</span>
       <span className="text-sm font-medium text-red-600">
        {formatNumber(userData.kycStats.rejected)}
       </span>
      </div>
      <div className="flex items-center justify-between">
       <span className="text-sm text-gray-600">En attente</span>
       <span className="text-sm font-medium text-yellow-600">
        {formatNumber(userData.kycStats.pending)}
       </span>
      </div>
     </div>
    </div>

    {/* User Status Distribution */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Répartition par Statut</h2>
     <ResponsiveContainer width="100%" height={300}>
      <PieChart>
       <Pie
        data={userData.userSegmentation.byStatus}
        cx="50%"
        cy="50%"
        labelLine={false}
        label={({ account_status, percent }) => `${account_status} ${(percent * 100).toFixed(0)}%`}
        outerRadius={80}
        fill="#8884d8"
        dataKey="count"
       >
        {userData.userSegmentation.byStatus.map((entry, index) => (
         <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
        ))}
       </Pie>
       <Tooltip />
      </PieChart>
     </ResponsiveContainer>
    </div>
   </div>

   {/* Detailed Statistics */}
   <div className="bg-white shadow rounded-lg p-6">
    <h2 className="text-lg font-medium text-gray-900 mb-4">Statistiques Détaillées</h2>
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
     <div className="text-center">
      <div className="text-3xl font-bold text-blue-600">
       {userData.userStats.verifiedArtisans}
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Artisans Vérifiés
      </div>
     </div>
     <div className="text-center">
      <div className="text-3xl font-bold text-green-600">
       {userData.userRetention.toFixed(1)}%
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Taux de Rétention
      </div>
     </div>
     <div className="text-center">
      <div className="text-3xl font-bold text-purple-600">
       {((userData.kycStats.approved / Math.max(1, userData.kycStats.totalSubmissions)) * 100).toFixed(1)}%
      </div>
      <div className="text-sm text-gray-500 mt-1">
       Taux d'Approbation KYC
      </div>
     </div>
    </div>
   </div>
  </BackofficeLayout>
 );
}
