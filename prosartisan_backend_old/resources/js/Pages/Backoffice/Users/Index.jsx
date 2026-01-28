import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 MagnifyingGlassIcon,
 FunnelIcon,
 EyeIcon,
 CheckCircleIcon,
 XCircleIcon,
 ExclamationTriangleIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';

export default function UsersIndex({ users, filters, userTypes, accountStatuses }) {
 const [search, setSearch] = useState(filters.search || '');
 const [showFilters, setShowFilters] = useState(false);

 const handleSearch = (e) => {
  e.preventDefault();
  router.get('/backoffice/users', { ...filters, search }, { preserveState: true });
 };

 const handleFilter = (key, value) => {
  const newFilters = { ...filters, [key]: value };
  if (!value) delete newFilters[key];
  router.get('/backoffice/users', newFilters, { preserveState: true });
 };

 const clearFilters = () => {
  router.get('/backoffice/users', {}, { preserveState: true });
 };

 const getStatusBadge = (status) => {
  const badges = {
   PENDING: 'bg-yellow-100 text-yellow-800',
   ACTIVE: 'bg-green-100 text-green-800',
   SUSPENDED: 'bg-red-100 text-red-800',
  };

  const labels = {
   PENDING: 'En attente',
   ACTIVE: 'Actif',
   SUSPENDED: 'Suspendu',
  };

  return (
   <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
    {labels[status]}
   </span>
  );
 };

 const getKycBadge = (user) => {
  if (user.user_type === 'CLIENT') {
   return <span className="text-gray-400 text-sm">N/A</span>;
  }

  if (user.is_kyc_verified) {
   return (
    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
     <CheckCircleIcon className="w-3 h-3 mr-1" />
     Vérifié
    </span>
   );
  }

  if (user.verification_status) {
   return (
    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
     <ExclamationTriangleIcon className="w-3 h-3 mr-1" />
     En attente
    </span>
   );
  }

  return (
   <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
    <XCircleIcon className="w-3 h-3 mr-1" />
    Non soumis
   </span>
  );
 };

 const getUserTypeLabel = (type) => {
  const labels = {
   CLIENT: 'Client',
   ARTISAN: 'Artisan',
   FOURNISSEUR: 'Fournisseur',
   REFERENT_ZONE: 'Référent de Zone',
  };
  return labels[type] || type;
 };

 return (
  <BackofficeLayout>
   <Head title="Gestion des Utilisateurs" />

   <div className="mb-8">
    <h1 className="text-3xl font-bold text-gray-900">Gestion des Utilisateurs</h1>
    <p className="mt-2 text-sm text-gray-700">
     Gérez les comptes utilisateurs, les vérifications KYC et les suspensions
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
        placeholder="Rechercher par email ou nom d'entreprise..."
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
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Type d'utilisateur
        </label>
        <select
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         value={filters.user_type || ''}
         onChange={(e) => handleFilter('user_type', e.target.value)}
        >
         <option value="">Tous les types</option>
         {userTypes.map((type) => (
          <option key={type.value} value={type.value}>
           {type.label}
          </option>
         ))}
        </select>
       </div>

       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Statut du compte
        </label>
        <select
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         value={filters.account_status || ''}
         onChange={(e) => handleFilter('account_status', e.target.value)}
        >
         <option value="">Tous les statuts</option>
         {accountStatuses.map((status) => (
          <option key={status.value} value={status.value}>
           {status.label}
          </option>
         ))}
        </select>
       </div>

       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Statut KYC
        </label>
        <select
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         value={filters.kyc_status || ''}
         onChange={(e) => handleFilter('kyc_status', e.target.value)}
        >
         <option value="">Tous les statuts KYC</option>
         <option value="verified">Vérifié</option>
         <option value="pending">En attente</option>
         <option value="not_submitted">Non soumis</option>
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

   {/* Users Table */}
   <div className="bg-white shadow rounded-lg overflow-hidden">
    <div className="overflow-x-auto">
     <table className="min-w-full divide-y divide-gray-200">
      <thead className="bg-gray-50">
       <tr>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Utilisateur
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Type
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Statut
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         KYC
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Inscription
        </th>
        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
         Actions
        </th>
       </tr>
      </thead>
      <tbody className="bg-white divide-y divide-gray-200">
       {users.data.map((user) => (
        <tr key={user.id} className="hover:bg-gray-50">
         <td className="px-6 py-4 whitespace-nowrap">
          <div>
           <div className="text-sm font-medium text-gray-900">
            {user.business_name || user.email}
           </div>
           <div className="text-sm text-gray-500">
            {user.email}
           </div>
           {user.trade_category && (
            <div className="text-xs text-blue-600">
             {user.trade_category}
            </div>
           )}
          </div>
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
          {getUserTypeLabel(user.user_type)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          {getStatusBadge(user.account_status)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          {getKycBadge(user)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
          {new Date(user.created_at).toLocaleDateString('fr-FR')}
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
          <Link
           href={`/backoffice/users/${user.id}`}
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
    {users.links && (
     <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
      <div className="flex items-center justify-between">
       <div className="flex-1 flex justify-between sm:hidden">
        {users.prev_page_url && (
         <Link
          href={users.prev_page_url}
          className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Précédent
         </Link>
        )}
        {users.next_page_url && (
         <Link
          href={users.next_page_url}
          className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Suivant
         </Link>
        )}
       </div>
       <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
         <p className="text-sm text-gray-700">
          Affichage de <span className="font-medium">{users.from}</span> à{' '}
          <span className="font-medium">{users.to}</span> sur{' '}
          <span className="font-medium">{users.total}</span> résultats
         </p>
        </div>
        <div>
         <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
          {users.links.map((link, index) => (
           <Link
            key={index}
            href={link.url || '#'}
            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
              ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
             } ${index === 0 ? 'rounded-l-md' : ''} ${index === users.links.length - 1 ? 'rounded-r-md' : ''
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
