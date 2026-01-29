import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, router } from '@inertiajs/react';
import {
 CurrencyDollarIcon,
 ArrowTrendingUpIcon,
 CreditCardIcon,
 BanknotesIcon
} from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from 'recharts';

export default function AnalyticsRevenue({ revenueData, period }) {
 const [selectedPeriod, setSelectedPeriod] = useState(period);

 const handlePeriodChange = (newPeriod) => {
  setSelectedPeriod(newPeriod);
  router.get('/backoffice/analytics/revenue', { period: newPeriod }, { preserveState: true });
 };

 const formatAmount = (centimes) => {
  return new Intl.NumberFormat('fr-FR', {
   style: 'currency',
   currency: 'XOF',
   minimumFractionDigits: 0,
  }).format(centimes / 100);
 };

 const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

 return (
  <BackofficeLayout>
   <Head title="Analytics - Revenus" />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div>
      <h1 className="text-3xl font-bold text-gray-900">Analytics - Revenus</h1>
      <p className="mt-2 text-sm text-gray-700">
       Analyse détaillée des revenus de la plateforme
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

   {/* Revenue Overview Cards */}
   <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div className="bg-white overflow-hidden shadow rounded-lg">
     <div className="p-5">
      <div className="flex items-center">
       <div className="flex-shrink-0">
        <CurrencyDollarIcon className="h-8 w-8 text-green-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Revenus Totaux
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatAmount(revenueData.totalRevenue)}
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
        <ArrowTrendingUpIcon className="h-8 w-8 text-blue-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Commissions Totales
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatAmount(revenueData.commissionData.totalCommissions)}
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
        <CreditCardIcon className="h-8 w-8 text-purple-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Commission Moyenne
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          {formatAmount(revenueData.commissionData.averageCommissionPerTransaction)}
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
        <BanknotesIcon className="h-8 w-8 text-yellow-600" />
       </div>
       <div className="ml-5 w-0 flex-1">
        <dl>
         <dt className="text-sm font-medium text-gray-500 truncate">
          Taux de Commission
         </dt>
         <dd className="text-2xl font-semibold text-gray-900">
          5.0%
         </dd>
        </dl>
       </div>
      </div>
     </div>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    {/* Revenue Growth Chart */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Évolution des Revenus</h2>
     <ResponsiveContainer width="100%" height={300}>
      <LineChart data={revenueData.revenueGrowth}>
       <CartesianGrid strokeDasharray="3 3" />
       <XAxis dataKey="date" />
       <YAxis />
       <Tooltip formatter={(value) => [formatAmount(value), 'Revenus']} />
       <Line type="monotone" dataKey="revenue" stroke="#10b981" strokeWidth={2} />
      </LineChart>
     </ResponsiveContainer>
    </div>

    {/* Revenue by Type */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Revenus par Type</h2>
     <ResponsiveContainer width="100%" height={300}>
      <BarChart data={revenueData.revenueByType}>
       <CartesianGrid strokeDasharray="3 3" />
       <XAxis dataKey="type" />
       <YAxis />
       <Tooltip formatter={(value) => [formatAmount(value), 'Montant']} />
       <Bar dataKey="total" fill="#3b82f6" />
      </BarChart>
     </ResponsiveContainer>
    </div>
   </div>

   {/* Payment Methods Distribution */}
   <div className="bg-white shadow rounded-lg p-6 mb-8">
    <h2 className="text-lg font-medium text-gray-900 mb-4">Répartition par Méthode de Paiement</h2>
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
     <div>
      <ResponsiveContainer width="100%" height={300}>
       <PieChart>
        <Pie
         data={revenueData.paymentMethods}
         cx="50%"
         cy="50%"
         labelLine={false}
         label={({ gateway, percent }) => `${gateway || 'Inconnu'} ${(percent * 100).toFixed(0)}%`}
         outerRadius={80}
         fill="#8884d8"
         dataKey="volume"
        >
         {revenueData.paymentMethods.map((entry, index) => (
          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
         ))}
        </Pie>
        <Tooltip formatter={(value) => [formatAmount(value), 'Volume']} />
       </PieChart>
      </ResponsiveContainer>
     </div>
     <div className="space-y-4">
      <h3 className="text-md font-medium text-gray-900">Détails par Méthode</h3>
      {revenueData.paymentMethods.map((method, index) => (
       <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
        <div className="flex items-center">
         <div
          className="w-4 h-4 rounded-full mr-3"
          style={{ backgroundColor: COLORS[index % COLORS.length] }}
         />
         <span className="text-sm font-medium text-gray-900">
          {method.gateway || 'Méthode inconnue'}
         </span>
        </div>
        <div className="text-right">
         <div className="text-sm font-medium text-gray-900">
          {formatAmount(method.volume)}
         </div>
         <div className="text-xs text-gray-500">
          {method.count} transactions
         </div>
        </div>
       </div>
      ))}
     </div>
    </div>
   </div>
  </BackofficeLayout>
 );
}
