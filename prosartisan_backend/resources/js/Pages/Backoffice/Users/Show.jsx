import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 CheckCircleIcon,
 XCircleIcon,
 ExclamationTriangleIcon,
 DocumentTextIcon,
 CameraIcon,
 BanknotesIcon,
 ChartBarIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function UserShow({ user, stats }) {
 const [showSuspendModal, setShowSuspendModal] = useState(false);
 const [showRejectKycModal, setShowRejectKycModal] = useState(false);
 const [suspendReason, setSuspendReason] = useState('');
 const [rejectReason, setRejectReason] = useState('');
 const [showKycDocuments, setShowKycDocuments] = useState(false);

 const handleSuspend = () => {
  if (!suspendReason.trim()) return;

  router.post(`/backoffice/users/${user.id}/suspend`, {
   reason: suspendReason
  }, {
   onSuccess: () => {
    setShowSuspendModal(false);
    setSuspendReason('');
   }
  });
 };

 const handleActivate = () => {
  router.post(`/backoffice/users/${user.id}/activate`);
 };

 const handleApproveKyc = () => {
  router.post(`/backoffice/users/${user.id}/approve-kyc`);
 };

 const handleRejectKyc = () => {
  if (!rejectReason.trim()) return;

  router.post(`/backoffice/users/${user.id}/reject-kyc`, {
   reason: rejectReason
  }, {
   onSuccess: () => {
    setShowRejectKycModal(false);
    setRejectReason('');
   }
  });
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
   <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badges[status]}`}>
    {labels[status]}
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

 const formatAmount = (centimes) => {
  return new Intl.NumberFormat('fr-FR', {
   style: 'currency',
   currency: 'XOF',
   minimumFractionDigits: 0,
  }).format(centimes / 100);
 };

 return (
  <BackofficeLayout>
   <Head title={`Utilisateur - ${user.email}`} />

   <div className="mb-8">
    <div className="flex items-center mb-4">
     <Link
      href="/backoffice/users"
      className="mr-4 p-2 text-gray-400 hover:text-gray-600"
     >
      <ArrowLeftIcon className="h-5 w-5" />
     </Link>
     <div>
      <h1 className="text-3xl font-bold text-gray-900">
       {user.business_name || user.email}
      </h1>
      <p className="mt-2 text-sm text-gray-700">
       {getUserTypeLabel(user.user_type)} • Inscrit le {new Date(user.created_at).toLocaleDateString('fr-FR')}
      </p>
     </div>
    </div>

    <div className="flex items-center space-x-4">
     {getStatusBadge(user.account_status)}

     {user.account_status === 'ACTIVE' ? (
      <Button
       variant="danger"
       size="sm"
       onClick={() => setShowSuspendModal(true)}
      >
       Suspendre le compte
      </Button>
     ) : user.account_status === 'SUSPENDED' ? (
      <Button
       variant="success"
       size="sm"
       onClick={handleActivate}
      >
       Activer le compte
      </Button>
     ) : null}
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {/* User Information */}
    <div className="lg:col-span-2 space-y-6">
     {/* Basic Info */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Informations de base</h2>
      <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
       <div>
        <dt className="text-sm font-medium text-gray-500">Email</dt>
        <dd className="mt-1 text-sm text-gray-900">{user.email}</dd>
       </div>
       <div>
        <dt className="text-sm font-medium text-gray-500">Type d'utilisateur</dt>
        <dd className="mt-1 text-sm text-gray-900">{getUserTypeLabel(user.user_type)}</dd>
       </div>
       <div>
        <dt className="text-sm font-medium text-gray-500">Numéro de téléphone</dt>
        <dd className="mt-1 text-sm text-gray-900">{user.phone_number || 'Non renseigné'}</dd>
       </div>
       <div>
        <dt className="text-sm font-medium text-gray-500">Statut du compte</dt>
        <dd className="mt-1">{getStatusBadge(user.account_status)}</dd>
       </div>
       {user.artisan_profile && (
        <>
         <div>
          <dt className="text-sm font-medium text-gray-500">Catégorie de métier</dt>
          <dd className="mt-1 text-sm text-gray-900">{user.artisan_profile.trade_category}</dd>
         </div>
         <div>
          <dt className="text-sm font-medium text-gray-500">KYC vérifié</dt>
          <dd className="mt-1">
           {user.artisan_profile.is_kyc_verified ? (
            <span className="inline-flex items-center text-green-600">
             <CheckCircleIcon className="h-4 w-4 mr-1" />
             Vérifié
            </span>
           ) : (
            <span className="inline-flex items-center text-red-600">
             <XCircleIcon className="h-4 w-4 mr-1" />
             Non vérifié
            </span>
           )}
          </dd>
         </div>
        </>
       )}
       {user.fournisseur_profile && (
        <div>
         <dt className="text-sm font-medium text-gray-500">Nom de l'entreprise</dt>
         <dd className="mt-1 text-sm text-gray-900">{user.fournisseur_profile.business_name}</dd>
        </div>
       )}
      </dl>
     </div>

     {/* KYC Information */}
     {user.kyc_verification && (
      <div className="bg-white shadow rounded-lg p-6">
       <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-medium text-gray-900">Vérification KYC</h2>
        <Button
         variant="outline"
         size="sm"
         onClick={() => setShowKycDocuments(true)}
        >
         <DocumentTextIcon className="h-4 w-4 mr-2" />
         Voir les documents
        </Button>
       </div>

       <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
         <dt className="text-sm font-medium text-gray-500">Type de pièce d'identité</dt>
         <dd className="mt-1 text-sm text-gray-900">{user.kyc_verification.id_type}</dd>
        </div>
        <div>
         <dt className="text-sm font-medium text-gray-500">Numéro de pièce</dt>
         <dd className="mt-1 text-sm text-gray-900">{user.kyc_verification.id_number}</dd>
        </div>
        <div>
         <dt className="text-sm font-medium text-gray-500">Statut de vérification</dt>
         <dd className="mt-1">
          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.kyc_verification.verification_status === 'APPROVED'
            ? 'bg-green-100 text-green-800'
            : user.kyc_verification.verification_status === 'REJECTED'
             ? 'bg-red-100 text-red-800'
             : 'bg-yellow-100 text-yellow-800'
           }`}>
           {user.kyc_verification.verification_status === 'APPROVED' ? 'Approuvé' :
            user.kyc_verification.verification_status === 'REJECTED' ? 'Rejeté' : 'En attente'}
          </span>
         </dd>
        </div>
        <div>
         <dt className="text-sm font-medium text-gray-500">Date de soumission</dt>
         <dd className="mt-1 text-sm text-gray-900">
          {new Date(user.kyc_verification.created_at).toLocaleDateString('fr-FR')}
         </dd>
        </div>
       </dl>

       {user.kyc_verification.verification_status === 'PENDING' && (
        <div className="mt-6 flex space-x-3">
         <Button
          variant="success"
          size="sm"
          onClick={handleApproveKyc}
         >
          <CheckCircleIcon className="h-4 w-4 mr-2" />
          Approuver
         </Button>
         <Button
          variant="danger"
          size="sm"
          onClick={() => setShowRejectKycModal(true)}
         >
          <XCircleIcon className="h-4 w-4 mr-2" />
          Rejeter
         </Button>
        </div>
       )}
      </div>
     )}
    </div>

    {/* Statistics */}
    <div className="space-y-6">
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Statistiques</h2>
      <div className="space-y-4">
       {user.user_type === 'CLIENT' && (
        <div className="flex items-center justify-between">
         <div className="flex items-center">
          <ChartBarIcon className="h-5 w-5 text-blue-500 mr-2" />
          <span className="text-sm text-gray-600">Missions créées</span>
         </div>
         <span className="text-sm font-medium text-gray-900">{stats.missions_created}</span>
        </div>
       )}

       {user.user_type === 'ARTISAN' && (
        <>
         <div className="flex items-center justify-between">
          <div className="flex items-center">
           <DocumentTextIcon className="h-5 w-5 text-green-500 mr-2" />
           <span className="text-sm text-gray-600">Devis soumis</span>
          </div>
          <span className="text-sm font-medium text-gray-900">{stats.quotes_submitted}</span>
         </div>
         <div className="flex items-center justify-between">
          <div className="flex items-center">
           <CheckCircleIcon className="h-5 w-5 text-purple-500 mr-2" />
           <span className="text-sm text-gray-600">Chantiers terminés</span>
          </div>
          <span className="text-sm font-medium text-gray-900">{stats.chantiers_completed}</span>
         </div>
        </>
       )}

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <BanknotesIcon className="h-5 w-5 text-yellow-500 mr-2" />
         <span className="text-sm text-gray-600">Volume transactions</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {formatAmount(stats.total_transactions)}
        </span>
       </div>

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <ExclamationTriangleIcon className="h-5 w-5 text-red-500 mr-2" />
         <span className="text-sm text-gray-600">Litiges impliqués</span>
        </div>
        <span className="text-sm font-medium text-gray-900">{stats.disputes_involved}</span>
       </div>
      </div>
     </div>
    </div>
   </div>

   {/* Suspend Account Modal */}
   <Modal
    isOpen={showSuspendModal}
    onClose={() => setShowSuspendModal(false)}
    title="Suspendre le compte"
    maxWidth="max-w-md"
   >
    <div className="space-y-4">
     <p className="text-sm text-gray-600">
      Veuillez indiquer la raison de la suspension de ce compte.
     </p>
     <textarea
      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
      rows={4}
      placeholder="Raison de la suspension..."
      value={suspendReason}
      onChange={(e) => setSuspendReason(e.target.value)}
     />
     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowSuspendModal(false)}
      >
       Annuler
      </Button>
      <Button
       variant="danger"
       onClick={handleSuspend}
       disabled={!suspendReason.trim()}
      >
       Suspendre
      </Button>
     </div>
    </div>
   </Modal>

   {/* Reject KYC Modal */}
   <Modal
    isOpen={showRejectKycModal}
    onClose={() => setShowRejectKycModal(false)}
    title="Rejeter la vérification KYC"
    maxWidth="max-w-md"
   >
    <div className="space-y-4">
     <p className="text-sm text-gray-600">
      Veuillez indiquer la raison du rejet de la vérification KYC.
     </p>
     <textarea
      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
      rows={4}
      placeholder="Raison du rejet..."
      value={rejectReason}
      onChange={(e) => setRejectReason(e.target.value)}
     />
     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowRejectKycModal(false)}
      >
       Annuler
      </Button>
      <Button
       variant="danger"
       onClick={handleRejectKyc}
       disabled={!rejectReason.trim()}
      >
       Rejeter
      </Button>
     </div>
    </div>
   </Modal>

   {/* KYC Documents Modal */}
   {user.kyc_verification && (
    <Modal
     isOpen={showKycDocuments}
     onClose={() => setShowKycDocuments(false)}
     title="Documents KYC"
     maxWidth="max-w-4xl"
    >
     <div className="space-y-6">
      <div>
       <h3 className="text-sm font-medium text-gray-900 mb-2">Pièce d'identité</h3>
       <img
        src={user.kyc_verification.id_document_url}
        alt="Pièce d'identité"
        className="max-w-full h-auto rounded-lg border border-gray-200"
       />
      </div>
      <div>
       <h3 className="text-sm font-medium text-gray-900 mb-2">Selfie</h3>
       <img
        src={user.kyc_verification.selfie_url}
        alt="Selfie"
        className="max-w-full h-auto rounded-lg border border-gray-200"
       />
      </div>
     </div>
    </Modal>
   )}
  </BackofficeLayout>
 );
}
