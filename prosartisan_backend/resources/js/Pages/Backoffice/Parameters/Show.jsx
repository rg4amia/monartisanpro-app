import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 PencilIcon,
 TrashIcon,
 CheckCircleIcon,
 XCircleIcon,
 LockClosedIcon,
 GlobeAltIcon,
 ClockIcon,
 UserIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function ParametersShow({ parameter, parameterTypes }) {
 const [isEditing, setIsEditing] = useState(false);
 const [showDeleteModal, setShowDeleteModal] = useState(false);
 const [editForm, setEditForm] = useState({
  label: parameter.label,
  value: parameter.value,
  type: parameter.type,
  category: parameter.category,
  description: parameter.description || '',
  is_public: parameter.is_public,
  is_editable: parameter.is_editable,
  validation_rules: parameter.validation_rules || []
 });

 const handleUpdate = (e) => {
  e.preventDefault();
  router.put(`/backoffice/parameters/${parameter.id}`, editForm, {
   onSuccess: () => {
    setIsEditing(false);
   }
  });
 };

 const handleQuickValueUpdate = (newValue) => {
  router.patch(`/backoffice/parameters/${parameter.id}/value`, {
   value: newValue
  });
 };

 const handleDelete = () => {
  router.delete(`/backoffice/parameters/${parameter.id}`, {
   onSuccess: () => {
    router.visit('/backoffice/parameters');
   }
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

 const formatValue = (param) => {
  switch (param.type) {
   case 'boolean':
    return param.value === '1' ? 'Oui' : 'Non';
   case 'json':
    try {
     return JSON.stringify(JSON.parse(param.value), null, 2);
    } catch {
     return param.value;
    }
   case 'percentage':
    return `${param.value}%`;
   default:
    return param.value;
  }
 };

 const renderValueInput = () => {
  if (parameter.type === 'boolean') {
   return (
    <select
     value={editForm.value}
     onChange={(e) => setEditForm({ ...editForm, value: e.target.value })}
     className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
    >
     <option value="1">Oui</option>
     <option value="0">Non</option>
    </select>
   );
  } else if (parameter.type === 'json') {
   return (
    <textarea
     value={editForm.value}
     onChange={(e) => setEditForm({ ...editForm, value: e.target.value })}
     className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
     rows={6}
    />
   );
  } else {
   return (
    <input
     type={parameter.type === 'integer' || parameter.type === 'float' || parameter.type === 'percentage' ? 'number' : 'text'}
     value={editForm.value}
     onChange={(e) => setEditForm({ ...editForm, value: e.target.value })}
     className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
    />
   );
  }
 };

 const renderQuickEdit = () => {
  const [quickValue, setQuickValue] = useState(parameter.value);

  if (!parameter.is_editable) return null;

  return (
   <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
    <h4 className="text-sm font-medium text-yellow-800 mb-2">Modification rapide</h4>
    <div className="flex space-x-2">
     {parameter.type === 'boolean' ? (
      <select
       value={quickValue}
       onChange={(e) => setQuickValue(e.target.value)}
       className="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
      >
       <option value="1">Oui</option>
       <option value="0">Non</option>
      </select>
     ) : parameter.type === 'json' ? (
      <textarea
       value={quickValue}
       onChange={(e) => setQuickValue(e.target.value)}
       className="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       rows={3}
      />
     ) : (
      <input
       type={parameter.type === 'integer' || parameter.type === 'float' || parameter.type === 'percentage' ? 'number' : 'text'}
       value={quickValue}
       onChange={(e) => setQuickValue(e.target.value)}
       className="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
      />
     )}
     <Button
      onClick={() => handleQuickValueUpdate(quickValue)}
      disabled={quickValue === parameter.value}
     >
      Mettre à jour
     </Button>
    </div>
   </div>
  );
 };

 return (
  <BackofficeLayout>
   <Head title={`Paramètre: ${parameter.label}`} />

   <div className="space-y-6">
    {/* Header */}
    <div className="flex items-center justify-between">
     <div className="flex items-center space-x-4">
      <Link
       href="/backoffice/parameters"
       className="flex items-center text-gray-600 hover:text-gray-900"
      >
       <ArrowLeftIcon className="h-5 w-5 mr-2" />
       Retour aux paramètres
      </Link>
     </div>
     <div className="flex space-x-3">
      {parameter.is_editable && (
       <>
        <Button
         variant="outline"
         onClick={() => setIsEditing(!isEditing)}
         icon={PencilIcon}
        >
         {isEditing ? 'Annuler' : 'Modifier'}
        </Button>
        <Button
         variant="danger"
         onClick={() => setShowDeleteModal(true)}
         icon={TrashIcon}
        >
         Supprimer
        </Button>
       </>
      )}
     </div>
    </div>

    {/* Parameter Details */}
    <div className="bg-white shadow rounded-lg">
     <div className="px-6 py-4 border-b border-gray-200">
      <div className="flex items-center justify-between">
       <div>
        <h1 className="text-2xl font-bold text-gray-900">{parameter.label}</h1>
        <p className="text-sm text-gray-500 mt-1">{parameter.key}</p>
       </div>
       <div className="flex items-center space-x-2">
        {getTypeBadge(parameter.type)}
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
         {parameter.category}
        </span>
       </div>
      </div>
     </div>

     <div className="px-6 py-4">
      {isEditing ? (
       <form onSubmit={handleUpdate} className="space-y-6">
        <div className="grid grid-cols-2 gap-6">
         <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
           Libellé
          </label>
          <input
           type="text"
           value={editForm.label}
           onChange={(e) => setEditForm({ ...editForm, label: e.target.value })}
           className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
          />
         </div>
         <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
           Catégorie
          </label>
          <input
           type="text"
           value={editForm.category}
           onChange={(e) => setEditForm({ ...editForm, category: e.target.value })}
           className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
          />
         </div>
        </div>

        <div>
         <label className="block text-sm font-medium text-gray-700 mb-2">
          Type
         </label>
         <select
          value={editForm.type}
          onChange={(e) => setEditForm({ ...editForm, type: e.target.value })}
          className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
         >
          {Object.entries(parameterTypes).map(([key, label]) => (
           <option key={key} value={key}>{label}</option>
          ))}
         </select>
        </div>

        <div>
         <label className="block text-sm font-medium text-gray-700 mb-2">
          Valeur
         </label>
         {renderValueInput()}
        </div>

        <div>
         <label className="block text-sm font-medium text-gray-700 mb-2">
          Description
         </label>
         <textarea
          value={editForm.description}
          onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
          className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
          rows={3}
         />
        </div>

        <div className="flex space-x-6">
         <label className="flex items-center">
          <input
           type="checkbox"
           checked={editForm.is_public}
           onChange={(e) => setEditForm({ ...editForm, is_public: e.target.checked })}
           className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span className="ml-2 text-sm text-gray-700">Public</span>
         </label>
         <label className="flex items-center">
          <input
           type="checkbox"
           checked={editForm.is_editable}
           onChange={(e) => setEditForm({ ...editForm, is_editable: e.target.checked })}
           className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span className="ml-2 text-sm text-gray-700">Modifiable</span>
         </label>
        </div>

        <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
         <Button
          type="button"
          variant="outline"
          onClick={() => setIsEditing(false)}
         >
          Annuler
         </Button>
         <Button type="submit">
          Sauvegarder
         </Button>
        </div>
       </form>
      ) : (
       <div className="space-y-6">
        {/* Current Value */}
        <div>
         <h3 className="text-lg font-medium text-gray-900 mb-4">Valeur actuelle</h3>
         <div className="bg-gray-50 rounded-md p-4">
          <pre className="text-sm text-gray-900 whitespace-pre-wrap">
           {formatValue(parameter)}
          </pre>
         </div>
        </div>

        {/* Quick Edit */}
        {renderQuickEdit()}

        {/* Description */}
        {parameter.description && (
         <div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Description</h3>
          <p className="text-gray-700">{parameter.description}</p>
         </div>
        )}

        {/* Properties */}
        <div>
         <h3 className="text-lg font-medium text-gray-900 mb-4">Propriétés</h3>
         <div className="grid grid-cols-2 gap-6">
          <div className="space-y-4">
           <div className="flex items-center">
            {parameter.is_editable ? (
             <CheckCircleIcon className="h-5 w-5 text-green-500 mr-2" />
            ) : (
             <LockClosedIcon className="h-5 w-5 text-red-500 mr-2" />
            )}
            <span className="text-sm text-gray-700">
             {parameter.is_editable ? 'Modifiable' : 'Non modifiable'}
            </span>
           </div>
           <div className="flex items-center">
            {parameter.is_public ? (
             <GlobeAltIcon className="h-5 w-5 text-blue-500 mr-2" />
            ) : (
             <XCircleIcon className="h-5 w-5 text-gray-400 mr-2" />
            )}
            <span className="text-sm text-gray-700">
             {parameter.is_public ? 'Public' : 'Privé'}
            </span>
           </div>
          </div>
          <div className="space-y-4">
           <div className="flex items-center">
            <ClockIcon className="h-5 w-5 text-gray-400 mr-2" />
            <span className="text-sm text-gray-700">
             Modifié le {new Date(parameter.updated_at).toLocaleDateString('fr-FR')}
            </span>
           </div>
           {parameter.updated_by && (
            <div className="flex items-center">
             <UserIcon className="h-5 w-5 text-gray-400 mr-2" />
             <span className="text-sm text-gray-700">
              Par {parameter.updated_by.email}
             </span>
            </div>
           )}
          </div>
         </div>
        </div>

        {/* Validation Rules */}
        {parameter.validation_rules && parameter.validation_rules.length > 0 && (
         <div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Règles de validation</h3>
          <div className="bg-gray-50 rounded-md p-4">
           <pre className="text-sm text-gray-700">
            {JSON.stringify(parameter.validation_rules, null, 2)}
           </pre>
          </div>
         </div>
        )}
       </div>
      )}
     </div>
    </div>
   </div>

   {/* Delete Confirmation Modal */}
   <Modal show={showDeleteModal} onClose={() => setShowDeleteModal(false)}>
    <div className="p-6">
     <div className="flex items-center mb-4">
      <div className="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
       <TrashIcon className="h-6 w-6 text-red-600" />
      </div>
     </div>
     <div className="text-center">
      <h3 className="text-lg font-medium text-gray-900 mb-2">
       Supprimer le paramètre
      </h3>
      <p className="text-sm text-gray-500 mb-4">
       Êtes-vous sûr de vouloir supprimer le paramètre "{parameter.label}" ?
       Cette action est irréversible.
      </p>
      <div className="flex justify-center space-x-3">
       <Button
        variant="outline"
        onClick={() => setShowDeleteModal(false)}
       >
        Annuler
       </Button>
       <Button
        variant="danger"
        onClick={handleDelete}
       >
        Supprimer
       </Button>
      </div>
     </div>
    </div>
   </Modal>
  </BackofficeLayout>
 );
}
