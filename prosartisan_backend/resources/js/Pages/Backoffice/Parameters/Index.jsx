import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 MagnifyingGlassIcon,
 FunnelIcon,
 EyeIcon,
 PencilIcon,
 TrashIcon,
 PlusIcon,
 DocumentArrowDownIcon,
 CogIcon,
 CheckCircleIcon,
 XCircleIcon,
 LockClosedIcon,
 GlobeAltIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function ParametersIndex({ parameters, filters, categories, stats, parameterTypes }) {
 const [search, setSearch] = useState(filters.search || '');
 const [showFilters, setShowFilters] = useState(false);
 const [showCreateModal, setShowCreateModal] = useState(false);
 const [showBulkEditModal, setShowBulkEditModal] = useState(false);
 const [selectedParameters, setSelectedParameters] = useState([]);
 const [bulkEditValues, setBulkEditValues] = useState({});
 const [createForm, setCreateForm] = useState({
  key: '',
  label: '',
  value: '',
  type: 'string',
  category: 'general',
  description: '',
  is_public: false,
  is_editable: true,
  validation_rules: []
 });

 const handleSearch = (e) => {
  e.preventDefault();
  router.get('/backoffice/parameters', { ...filters, search }, { preserveState: true });
 };

 const handleFilter = (key, value) => {
  const newFilters = { ...filters, [key]: value };
  if (!value) delete newFilters[key];
  router.get('/backoffice/parameters', newFilters, { preserveState: true });
 };

 const clearFilters = () => {
  router.get('/backoffice/parameters', {}, { preserveState: true });
 };

 const handleCreateParameter = (e) => {
  e.preventDefault();
  router.post('/backoffice/parameters', createForm, {
   onSuccess: () => {
    setShowCreateModal(false);
    setCreateForm({
     key: '',
     label: '',
     value: '',
     type: 'string',
     category: 'general',
     description: '',
     is_public: false,
     is_editable: true,
     validation_rules: []
    });
   }
  });
 };

 const handleBulkEdit = () => {
  router.post('/backoffice/parameters/bulk-update', {
   parameters: bulkEditValues
  }, {
   onSuccess: () => {
    setShowBulkEditModal(false);
    setSelectedParameters([]);
    setBulkEditValues({});
   }
  });
 };

 const handleParameterSelection = (parameterId, isSelected) => {
  if (isSelected) {
   setSelectedParameters([...selectedParameters, parameterId]);
  } else {
   setSelectedParameters(selectedParameters.filter(id => id !== parameterId));
  }
 };

 const handleBulkValueChange = (parameterKey, value) => {
  setBulkEditValues({
   ...bulkEditValues,
   [parameterKey]: value
  });
 };

 const getTypeBadge = (type) => {
  const badges = {
   string: 'bg-blue-100 text-blue-800',
   integer: 'bg-green-100 text-green-800',
   float: 'bg-yellow-100 text-yellow-800',
   boolean: 'bg-purple-100 text-purple-800',
   json: 'bg-indigo-100 text-indigo-800',
   email: 'bg-pink-100 text-pink-800',
   url: 'bg-cyan-100 text-cyan-800',
   percentage: 'bg-orange-100 text-orange-800',
  };

  return (
   <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[type]}`}>
    {parameterTypes[type]}
   </span>
  );
 };

 const formatValue = (parameter) => {
  switch (parameter.type) {
   case 'boolean':
    return parameter.value === '1' ? 'Oui' : 'Non';
   case 'json':
    try {
     return JSON.stringify(JSON.parse(parameter.value), null, 2);
    } catch {
     return parameter.value;
    }
   case 'percentage':
    return `${parameter.value}%`;
   default:
    return parameter.value;
  }
 };

 return (
  <BackofficeLayout>
   <Head title="Gestion des Paramètres" />

   <div className="space-y-6">
    {/* Header */}
    <div className="flex justify-between items-center">
     <div>
      <h1 className="text-2xl font-bold text-gray-900">Paramètres Système</h1>
      <p className="text-gray-600">Gérez les paramètres de configuration de la plateforme</p>
     </div>
     <div className="flex space-x-3">
      <Button
       variant="outline"
       onClick={() => window.open('/backoffice/parameters/export', '_blank')}
       icon={DocumentArrowDownIcon}
      >
       Exporter
      </Button>
      <Button
       onClick={() => setShowCreateModal(true)}
       icon={PlusIcon}
      >
       Nouveau paramètre
      </Button>
     </div>
    </div>

    {/* Stats Cards */}
    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
     <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center">
       <CogIcon className="h-8 w-8 text-blue-600" />
       <div className="ml-4">
        <p className="text-sm font-medium text-gray-600">Total</p>
        <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
       </div>
      </div>
     </div>
     <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center">
       <PencilIcon className="h-8 w-8 text-green-600" />
       <div className="ml-4">
        <p className="text-sm font-medium text-gray-600">Modifiables</p>
        <p className="text-2xl font-bold text-gray-900">{stats.editable}</p>
       </div>
      </div>
     </div>
     <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center">
       <GlobeAltIcon className="h-8 w-8 text-purple-600" />
       <div className="ml-4">
        <p className="text-sm font-medium text-gray-600">Publics</p>
        <p className="text-2xl font-bold text-gray-900">{stats.public}</p>
       </div>
      </div>
     </div>
     <div className="bg-white p-6 rounded-lg shadow">
      <div className="flex items-center">
       <CheckCircleIcon className="h-8 w-8 text-orange-600" />
       <div className="ml-4">
        <p className="text-sm font-medium text-gray-600">Catégories</p>
        <p className="text-2xl font-bold text-gray-900">{stats.categories}</p>
       </div>
      </div>
     </div>
    </div>

    {/* Search and Filters */}
    <div className="bg-white p-6 rounded-lg shadow">
     <div className="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
      <form onSubmit={handleSearch} className="flex-1 max-w-md">
       <div className="relative">
        <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
        <input
         type="text"
         placeholder="Rechercher par clé, libellé ou description..."
         value={search}
         onChange={(e) => setSearch(e.target.value)}
         className="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
        />
       </div>
      </form>

      <div className="flex items-center space-x-4">
       <button
        onClick={() => setShowFilters(!showFilters)}
        className="flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
       >
        <FunnelIcon className="h-4 w-4 mr-2" />
        Filtres
       </button>

       {selectedParameters.length > 0 && (
        <Button
         variant="outline"
         onClick={() => setShowBulkEditModal(true)}
        >
         Modifier sélection ({selectedParameters.length})
        </Button>
       )}
      </div>
     </div>

     {/* Filters Panel */}
     {showFilters && (
      <div className="mt-4 p-4 bg-gray-50 rounded-md">
       <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
         <label className="block text-sm font-medium text-gray-700 mb-1">
          Catégorie
         </label>
         <select
          value={filters.category || ''}
          onChange={(e) => handleFilter('category', e.target.value)}
          className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         >
          <option value="">Toutes les catégories</option>
          {categories.map(category => (
           <option key={category} value={category}>
            {category}
           </option>
          ))}
         </select>
        </div>

        <div>
         <label className="block text-sm font-medium text-gray-700 mb-1">
          Modifiable
         </label>
         <select
          value={filters.is_editable || ''}
          onChange={(e) => handleFilter('is_editable', e.target.value)}
          className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         >
          <option value="">Tous</option>
          <option value="1">Modifiable</option>
          <option value="0">Non modifiable</option>
         </select>
        </div>

        <div>
         <label className="block text-sm font-medium text-gray-700 mb-1">
          Public
         </label>
         <select
          value={filters.is_public || ''}
          onChange={(e) => handleFilter('is_public', e.target.value)}
          className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         >
          <option value="">Tous</option>
          <option value="1">Public</option>
          <option value="0">Privé</option>
         </select>
        </div>

        <div className="flex items-end">
         <Button
          variant="outline"
          onClick={clearFilters}
          className="w-full"
         >
          Réinitialiser
         </Button>
        </div>
       </div>
      </div>
     )}
    </div>

    {/* Parameters Table */}
    <div className="bg-white shadow rounded-lg overflow-hidden">
     <table className="min-w-full divide-y divide-gray-200">
      <thead className="bg-gray-50">
       <tr>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         <input
          type="checkbox"
          onChange={(e) => {
           if (e.target.checked) {
            setSelectedParameters(parameters.data.map(p => p.id));
           } else {
            setSelectedParameters([]);
           }
          }}
          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
         />
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Paramètre
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Valeur
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Type
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Catégorie
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Statut
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Actions
        </th>
       </tr>
      </thead>
      <tbody className="bg-white divide-y divide-gray-200">
       {parameters.data.map((parameter) => (
        <tr key={parameter.id} className="hover:bg-gray-50">
         <td className="px-6 py-4 whitespace-nowrap">
          <input
           type="checkbox"
           checked={selectedParameters.includes(parameter.id)}
           onChange={(e) => handleParameterSelection(parameter.id, e.target.checked)}
           className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          <div>
           <div className="text-sm font-medium text-gray-900">
            {parameter.label}
           </div>
           <div className="text-sm text-gray-500">
            {parameter.key}
           </div>
           {parameter.description && (
            <div className="text-xs text-gray-400 mt-1">
             {parameter.description}
            </div>
           )}
          </div>
         </td>
         <td className="px-6 py-4">
          <div className="text-sm text-gray-900 max-w-xs truncate">
           {formatValue(parameter)}
          </div>
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          {getTypeBadge(parameter.type)}
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
           {parameter.category}
          </span>
         </td>
         <td className="px-6 py-4 whitespace-nowrap">
          <div className="flex space-x-2">
           {parameter.is_editable ? (
            <CheckCircleIcon className="h-4 w-4 text-green-500" title="Modifiable" />
           ) : (
            <LockClosedIcon className="h-4 w-4 text-red-500" title="Non modifiable" />
           )}
           {parameter.is_public && (
            <GlobeAltIcon className="h-4 w-4 text-blue-500" title="Public" />
           )}
          </div>
         </td>
         <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
          <div className="flex space-x-2">
           <Link
            href={`/backoffice/parameters/${parameter.id}`}
            className="text-blue-600 hover:text-blue-900"
           >
            <EyeIcon className="h-4 w-4" />
           </Link>
           {parameter.is_editable && (
            <Link
             href={`/backoffice/parameters/${parameter.id}`}
             className="text-green-600 hover:text-green-900"
            >
             <PencilIcon className="h-4 w-4" />
            </Link>
           )}
          </div>
         </td>
        </tr>
       ))}
      </tbody>
     </table>

     {/* Pagination */}
     {parameters.links && (
      <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
       <div className="flex items-center justify-between">
        <div className="flex-1 flex justify-between sm:hidden">
         {parameters.prev_page_url && (
          <Link
           href={parameters.prev_page_url}
           className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
           Précédent
          </Link>
         )}
         {parameters.next_page_url && (
          <Link
           href={parameters.next_page_url}
           className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
           Suivant
          </Link>
         )}
        </div>
        <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
         <div>
          <p className="text-sm text-gray-700">
           Affichage de <span className="font-medium">{parameters.from}</span> à{' '}
           <span className="font-medium">{parameters.to}</span> sur{' '}
           <span className="font-medium">{parameters.total}</span> résultats
          </p>
         </div>
         <div>
          <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
           {parameters.links.map((link, index) => (
            <Link
             key={index}
             href={link.url || '#'}
             className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
               ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
               : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
              } ${index === 0 ? 'rounded-l-md' : ''} ${index === parameters.links.length - 1 ? 'rounded-r-md' : ''
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
   </div>

   {/* Create Parameter Modal */}
   <Modal show={showCreateModal} onClose={() => setShowCreateModal(false)}>
    <div className="p-6">
     <h3 className="text-lg font-medium text-gray-900 mb-4">
      Créer un nouveau paramètre
     </h3>
     <form onSubmit={handleCreateParameter} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Clé *
        </label>
        <input
         type="text"
         required
         value={createForm.key}
         onChange={(e) => setCreateForm({ ...createForm, key: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         placeholder="ex: app.maintenance_mode"
        />
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Libellé *
        </label>
        <input
         type="text"
         required
         value={createForm.label}
         onChange={(e) => setCreateForm({ ...createForm, label: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         placeholder="Mode maintenance"
        />
       </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Type *
        </label>
        <select
         required
         value={createForm.type}
         onChange={(e) => setCreateForm({ ...createForm, type: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
        >
         {Object.entries(parameterTypes).map(([key, label]) => (
          <option key={key} value={key}>{label}</option>
         ))}
        </select>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
         Catégorie *
        </label>
        <input
         type="text"
         required
         value={createForm.category}
         onChange={(e) => setCreateForm({ ...createForm, category: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         placeholder="general"
        />
       </div>
      </div>

      <div>
       <label className="block text-sm font-medium text-gray-700 mb-1">
        Valeur *
       </label>
       {createForm.type === 'boolean' ? (
        <select
         required
         value={createForm.value}
         onChange={(e) => setCreateForm({ ...createForm, value: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
        >
         <option value="">Sélectionner...</option>
         <option value="1">Oui</option>
         <option value="0">Non</option>
        </select>
       ) : createForm.type === 'json' ? (
        <textarea
         required
         value={createForm.value}
         onChange={(e) => setCreateForm({ ...createForm, value: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         rows={4}
         placeholder='{"key": "value"}'
        />
       ) : (
        <input
         type={createForm.type === 'integer' || createForm.type === 'float' || createForm.type === 'percentage' ? 'number' : 'text'}
         required
         value={createForm.value}
         onChange={(e) => setCreateForm({ ...createForm, value: e.target.value })}
         className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
        />
       )}
      </div>

      <div>
       <label className="block text-sm font-medium text-gray-700 mb-1">
        Description
       </label>
       <textarea
        value={createForm.description}
        onChange={(e) => setCreateForm({ ...createForm, description: e.target.value })}
        className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
        rows={2}
       />
      </div>

      <div className="flex space-x-4">
       <label className="flex items-center">
        <input
         type="checkbox"
         checked={createForm.is_public}
         onChange={(e) => setCreateForm({ ...createForm, is_public: e.target.checked })}
         className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span className="ml-2 text-sm text-gray-700">Public</span>
       </label>
       <label className="flex items-center">
        <input
         type="checkbox"
         checked={createForm.is_editable}
         onChange={(e) => setCreateForm({ ...createForm, is_editable: e.target.checked })}
         className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span className="ml-2 text-sm text-gray-700">Modifiable</span>
       </label>
      </div>

      <div className="flex justify-end space-x-3 pt-4">
       <Button
        type="button"
        variant="outline"
        onClick={() => setShowCreateModal(false)}
       >
        Annuler
       </Button>
       <Button type="submit">
        Créer
       </Button>
      </div>
     </form>
    </div>
   </Modal>

   {/* Bulk Edit Modal */}
   <Modal show={showBulkEditModal} onClose={() => setShowBulkEditModal(false)}>
    <div className="p-6">
     <h3 className="text-lg font-medium text-gray-900 mb-4">
      Modification en lot ({selectedParameters.length} paramètres)
     </h3>
     <div className="space-y-4 max-h-96 overflow-y-auto">
      {parameters.data
       .filter(p => selectedParameters.includes(p.id) && p.is_editable)
       .map(parameter => (
        <div key={parameter.id} className="border border-gray-200 rounded-md p-4">
         <div className="flex justify-between items-start mb-2">
          <div>
           <h4 className="font-medium text-gray-900">{parameter.label}</h4>
           <p className="text-sm text-gray-500">{parameter.key}</p>
          </div>
          {getTypeBadge(parameter.type)}
         </div>
         <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
           Nouvelle valeur
          </label>
          {parameter.type === 'boolean' ? (
           <select
            value={bulkEditValues[parameter.key] || parameter.value}
            onChange={(e) => handleBulkValueChange(parameter.key, e.target.value)}
            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
           >
            <option value="1">Oui</option>
            <option value="0">Non</option>
           </select>
          ) : parameter.type === 'json' ? (
           <textarea
            value={bulkEditValues[parameter.key] || parameter.value}
            onChange={(e) => handleBulkValueChange(parameter.key, e.target.value)}
            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
            rows={3}
           />
          ) : (
           <input
            type={parameter.type === 'integer' || parameter.type === 'float' || parameter.type === 'percentage' ? 'number' : 'text'}
            value={bulkEditValues[parameter.key] || parameter.value}
            onChange={(e) => handleBulkValueChange(parameter.key, e.target.value)}
            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
           />
          )}
         </div>
        </div>
       ))}
     </div>
     <div className="flex justify-end space-x-3 pt-4">
      <Button
       type="button"
       variant="outline"
       onClick={() => setShowBulkEditModal(false)}
      >
       Annuler
      </Button>
      <Button onClick={handleBulkEdit}>
       Mettre à jour
      </Button>
     </div>
    </div>
   </Modal>
  </BackofficeLayout>
 );
}
