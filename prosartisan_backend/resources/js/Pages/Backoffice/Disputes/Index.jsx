import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 MagnifyingGlassIcon,
 FunnelIcon,
 EyeIcon,
 ExclamationTriangleIcon,
 ClockIcon,
 CheckCircleIcon,
 XCircleIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';

export default function DisputesIndex({ disputes, filters, disputeStatuses, disputeTypes }) {
 const [search, setSearch] = useState(filters.search || '');
 const [showFilters, setShowFilters] = useState(false);

 const handleSearch = (e) => {
  e.preventDefault();
  router.get('/backoffice/disputes', { ...filters, search }, { preserveState: true });
 };

 const handleFilter = (key, value) => {
  const newFilters = { ...filters, [key]: value };
  if (!value) delete newFilters[key];
  router.get('/backoffice/disputes', newFilters, { preserveState: true });
 };

 const clearFilters = () => {
  router.get('/backoffice/disputes', {}, { preserveState: true });
 };

 const getStatusBadge = (status) => {
  const badges = {
   OPEN: 'bg-red-100 text-red-800',
   IN_MEDIATION: 'bg-yellow-100 text-yellow-800',
   IN_ARBITRATION: 'bg-blue-100 text-blue-800',
   RESOLVED: 'bg-green-100 text-green-800',
   CLOSED: 'bg-gray-100 text-gray-800',
  };

  const labels = {
   OPEN: 'Ouvert',
   IN_MEDIATION: 'En médiation',
   IN_ARBITRATION: 'En arbitrage',
   RESOLVED: 'Résolu',
   CLOSED: 'Fermé',
  };

  const icons = {
   OPEN: ExclamationTriangleIcon,
   IN_MEDIATION: ClockIcon,
   IN_ARBITRATION: ClockIcon,
   RESOLVED: CheckCircleIcon,
   CLOSED: XCircleIcon,
  };

  const Icon = icons[status];

  return (
   <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
    <Icon className="w-3 h-3 mr-1" />
    {labels[status]}
   </span>
  );
 };

 const getTypeBadge = (type) => {
  const badges = {
   QUALITY: 'bg-purple-100 text-purple-800',
   PAYMENT: 'bg-green-100 text-green-800',
   DELAY: 'bg-orange-100 text-orange-800',
   OTHER: 'bg-gray-100 text-gray-800',
  };

  const labels = {
   QUALITY: 'Qualité',
   PAYMENT: 'Paiement',
   DELAY: 'Retard',
   OTHER: 'Autre',
  };

  return (
   <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[type]}`}>
    {labels[type]}
   </span>
  );
 };

 const getPriorityBadge = (dispute) => {
  // High priority if recent and open
  const isRecent = new Date(dispute.created_at) > new Date(Date.now() - 24 * 60 * 60 * 1000);
  const isOpen = dispute.status === 'OPEN';

  if (isRecent && isOpen) {
   return (
    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
     Priorité haute
    </span>
   );
  }

  return null;
 };

 return (
  <BackofficeLayout>
   <Head title="Gestion des Litiges" />

   <div className="mb-8">
    <h1 className="text-3xl font-bold text-gray-900">Gestion des Litiges</h1>
    <p className="mt-2 text-sm text-gray-700">
     Gérez les litiges, assignez des médiateurs et rendez des décisions d'arbitrage
    </p>
   </div>

   {/* Search and Filters */}
   <div className="bg-white shadow rounded-lg p-6 mb-6">
    <div className="flex flex-col sm:flex-row gap-4">
     <form onSubmit={handleSearch} className="flex-1">
      <div className="relative">
       <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
       <input
        type="text"
        placeholder="Rechercher par description, mission ou utilisateur..."
        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
       />
      </div>
     </form>

     <Button
      variant="outline"
      onClick={() => setShowFilters(!showFilters)}
      className="flex items-center"
     >
      <FunnelIcon className="h-5 w-5 mr-2" />
      Filtres
     </Button>
    </div>

    {showFilters && (
     <div className="mt-4 pt-4 border-t border-gray-200">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Statut
        </label>
        <select
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         value={filters.status || ''}
         onChange={(e) => handleFilter('status', e.target.value)}
        >
         <option value="">Tous les statuts</option>
         {disputeStatuses.map((status) => (
          <option key={status.value} value={status.value}>
           {status.label}
          </option>
         ))}
        </select>
       </div>

       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Type de litige
        </label>
        <select
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         value={filters.type || ''}
         onChange={(e) => handleFilter('type', e.target.value)}
        >
         <option value="">Tous les types</option>
         {disputeTypes.map((type) => (
          <option key={type.value} value={type.value}>
           {type.label}
          </option>
         ))}
        </select>
       </div>
      </div>

      <div className="mt-4 flex justify-end">
       <Button variant="outline" onClick={clearFilters}>
        Effacer les filtres
       </Button>
      </div>
     </div>
    )}
   </div>

   {/* Disputes Table */}
   <div className="bg-white shadow rounded-lg overflow-hidden">
    <div className="overflow-x-auto">
     <table className="min-w-full divide-y divide-gray-200">
      <thead className="bg-gray-50">
       <tr>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Litige
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Parties
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Type
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Statut
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Médiateur
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Date
        </th>
        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
         Actions
        </th>
       </tr>
      </thead>
      <tbody className="bg-white divide-y divide-gray-200">
       {disputes.data.map((dispute) => (
        <tr key={dispute.id} className="hover:bg-gray-50">
         <td className="px-6 py-4">
          <div>
           <div className="text-sm font-medium text-gray-900 truncate max-w-xs">
            {dispute.description}
           </div>
           <div className="text-sm text-gray-500 truncate max-w-xs">
            Mission: {dispute.mission_description}
           </div>
           <div className="mt-1">
            {getPriorityBadge(dispute)}
           </div>
          </div>
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          <div className="text-sm text-gray-900">
           <div>Plaignant: {dispute.reporter_email}</div>
           <div>Défendeur: {dispute.defendant_email}</div>
          </div>
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          {getTypeBadge(dispute.type)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          {getStatusBadge(dispute.status)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {dispute.mediator_email || 'Non assigné'}
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {new Date(dispute.created_at).toLocaleDateString('fr-FR')}
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
          <Link
           href={`/backoffice/disputes/${dispute.id}`}
           className="text-blue-600 hover:text-blue-900 inline-flex items-center"
          >
           <EyeIcon className="h-4 w-4 mr-1" />
           Voir
          </Link>
         </td>
        </tr>
       ))}
      </tbody>
     </table>
    </div>

    {/* Pagination */}
    {disputes.links && (
     <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
      <div className="flex items-center justify-between">
       <div className="flex-1 flex justify-between sm:hidden">
        {disputes.prev_page_url && (
         <Link
          href={disputes.prev_page_url}
          className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Précédent
         </Link>
        )}
        {disputes.next_page_url && (
         <Link
          href={disputes.next_page_url}
          className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Suivant
         </Link>
        )}
       </div>
       <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
         <p className="text-sm text-gray-700">
          Affichage de <span className="font-medium">{disputes.from}</span> à{' '}
          <span className="font-medium">{disputes.to}</span> sur{' '}
          <span className="font-medium">{disputes.total}</span> résultats
         </p>
        </div>
        <div>
         <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
          {disputes.links.map((link, index) => (
           <Link
            key={index}
            href={link.url || '#'}
            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
             } ${index === 0 ? 'rounded-l-md' : ''} ${index === disputes.links.length - 1 ? 'rounded-r-md' : ''
             }`}
            dangerouslySetInnerHTML={{ __html: link.label }}
           />
          ))}
         </nav>
        </div>
       </div>
      </div>
     </div>
    )}
   </div>
  </BackofficeLayout>
 );
}
