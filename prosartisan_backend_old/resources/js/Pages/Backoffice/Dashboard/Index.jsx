import React from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head } from '@inertiajs/react';
import {
 UserGroupIcon,
 CurrencyDollarIcon,
 CheckCircleIcon,
 ClockIcon,
 ExclamationTriangleIcon
} from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar } from 'recharts';

export default function Dashboard({ stats = {}, chartData = [], disputeStats = [] }) {
 const cards = [
  {
   name: 'Artisans Actifs',
   value: stats.artisans_actifs || 0,
   icon: UserGroupIcon,
   color: 'bg-blue-500',
   change: '+12%',
   changeType: 'positive'
  },
  {
   name: 'Missions en Cours',
   value: stats.missions_en_cours || 0,
   icon: ClockIcon,
   color: 'bg-yellow-500',
   change: '+5%',
   changeType: 'positive'
  },
  {
   name: 'Volume Transactions (XOF)',
   value: stats.volume_transactions ? `${(stats.volume_transactions / 1000000).toFixed(1)}M` : '0',
   icon: CurrencyDollarIcon,
   color: 'bg-green-500',
   change: '+23%',
   changeType: 'positive'
  },
  {
   name: 'Chantiers Terminés',
   value: stats.chantiers_termines || 0,
   icon: CheckCircleIcon,
   color: 'bg-purple-500',
   change: '+8%',
   changeType: 'positive'
  },
  {
   name: 'Litiges Actifs',
   value: stats.litiges_actifs || 0,
   icon: ExclamationTriangleIcon,
   color: 'bg-red-500',
   change: '-2%',
   changeType: 'negative'
  },
 ];

 return (
  <BackofficeLayout>
   <Head title="Dashboard" />

   <div className="mb-8">
    <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p className="mt-2 text-sm text-gray-700">Vue d'ensemble de la plateforme ProSartisan</p>
   </div>

   {/* Stats Cards */}
   <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5 mb-8">
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
          <dd className="flex items-baseline">
           <div className="text-2xl font-semibold text-gray-900">
            {card.value}
           </div>
           <div className={`ml-2 flex items-baseline text-sm font-semibold ${card.changeType === 'positive' ? 'text-green-600' : 'text-red-600'
            }`}>
            {card.change}
           </div>
          </dd>
         </dl>
        </div>
       </div>
      </div>
     </div>
    ))}
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
    {/* Transactions Chart */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Transactions des 7 derniers jours</h2>
     <ResponsiveContainer width="100%" height={300}>
      <LineChart data={chartData}>
       <CartesianGrid strokeDasharray="3 3" />
       <XAxis dataKey="date" />
       <YAxis />
       <Tooltip formatter={(value) => [`${value} FCFA`, 'Montant']} />
       <Line type="monotone" dataKey="montant" stroke="#1E88E5" strokeWidth={2} />
      </LineChart>
     </ResponsiveContainer>
    </div>

    {/* Dispute Stats */}
    <div className="bg-white shadow rounded-lg p-6">
     <h2 className="text-lg font-medium text-gray-900 mb-4">Statut des Litiges</h2>
     <ResponsiveContainer width="100%" height={300}>
      <BarChart data={disputeStats}>
       <CartesianGrid strokeDasharray="3 3" />
       <XAxis dataKey="status" />
       <YAxis />
       <Tooltip />
       <Bar dataKey="count" fill="#8884d8" />
      </BarChart>
     </ResponsiveContainer>
    </div>
   </div>

   {/* Recent Activity */}
   <div className="mt-8 bg-white shadow rounded-lg">
    <div className="px-6 py-4 border-b border-gray-200">
     <h2 className="text-lg font-medium text-gray-900">Activité Récente</h2>
    </div>
    <div className="p-6">
     <div className="flow-root">
      <ul className="-mb-8">
       <li className="relative pb-8">
        <div className="relative flex space-x-3">
         <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-500">
          <CheckCircleIcon className="h-5 w-5 text-white" />
         </div>
         <div className="min-w-0 flex-1">
          <div>
           <p className="text-sm text-gray-500">
            Nouveau chantier terminé par <span className="font-medium text-gray-900">Jean Kouassi</span>
           </p>
           <p className="text-xs text-gray-400">Il y a 2 heures</p>
          </div>
         </div>
        </div>
       </li>
       <li className="relative pb-8">
        <div className="relative flex space-x-3">
         <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500">
          <UserGroupIcon className="h-5 w-5 text-white" />
         </div>
         <div className="min-w-0 flex-1">
          <div>
           <p className="text-sm text-gray-500">
            Nouvel artisan inscrit: <span className="font-medium text-gray-900">Marie Diabaté</span>
           </p>
           <p className="text-xs text-gray-400">Il y a 4 heures</p>
          </div>
         </div>
        </div>
       </li>
       <li className="relative">
        <div className="relative flex space-x-3">
         <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-500">
          <ExclamationTriangleIcon className="h-5 w-5 text-white" />
         </div>
         <div className="min-w-0 flex-1">
          <div>
           <p className="text-sm text-gray-500">
            Nouveau litige signalé pour la mission <span className="font-medium text-gray-900">#M-2024-001</span>
           </p>
           <p className="text-xs text-gray-400">Il y a 6 heures</p>
          </div>
         </div>
        </div>
       </li>
      </ul>
     </div>
    </div>
   </div>
  </BackofficeLayout>
 );
}
