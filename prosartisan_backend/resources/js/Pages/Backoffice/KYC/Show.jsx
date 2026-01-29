import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 CheckCircleIcon,
 XCircleIcon,
 ClockIcon,
 DocumentTextIcon,
 UserIcon,
 CalendarIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function KYCShow({ verification, history }) {
 const [showRejectModal, setShowRejectModal] = useState(false);
 const [rejectReason, setRejectReason] = useState('');
 const [isSubmitting, setIsSubmitting] = useState(false);

 const handleApprove = () => {
  setIsSubmitting(true);
  router.post(`/backoffice/kyc/${verification.id}/approve`, {}, {
   onFinish: () => setIsSubmitting(false)
  });
 };

 const handleReject = () => {
  if (!rejectReason.trim()) return;

  setIsSubmitting(true);
  router.post(`/backoffice/kyc/${verification.id}/reject`, {
   reason: rejectReason
  }, {
   onSuccess: () => {
    setShowRejectModal(false);
    setRejectReason('');
   },
   onFinish: () => setIsSubmitting(false)
  });
 };

 const getStatusBadge = (status) => {
  const badges = {
   PENDING: 'bg-yellow-100 text-yellow-800',
   APPROVED: 'bg-green-100 text-green-800',
   REJECTED: 'bg-red-100 text-red-800',
  };

  const labels = {
   PENDING: 'En attente',
   APPROVED: 'Approuvé',
   REJECTED: 'Rejeté',
  };

  const icons = {
   PENDING: ClockIcon,
   APPROVED: CheckCircleIcon,
   REJECTED: XCircleIcon,
  };

  const Icon = icons[status];

  return (
   <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badges[status]}`}>
    <Icon className="w-4 h-4 mr-2" />
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

 const getIdTypeLabel = (type) => {
  const labels = {
   CNI: 'Carte Nationale d\'Identité',
   PASSPORT: 'Passeport',
   DRIVING_LICENSE: 'Permis de Conduire',
  };
  return labels[type] || type;
 };

 const getActionLabel = (action) => {
  const labels = {
   kyc_submitted: 'Soumission KYC',
   kyc_approved: 'Approbation KYC',
   kyc_rejected: 'Rejet KYC',
  };
  return labels[action] || action;
 };

 return (
  <BackofficeLayout>
   <Head title={`KYC - ${verification.email}`} />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div className="flex items-center space-x-4">
      <Link
       href="/backoffice/kyc"
       className="flex items-center text-gray-500 hover:text-gray-700"
      >
       <ArrowLeftIcon className="h-5 w-5 mr-2" />
       Retour aux vérifications
      </Link>
      <div>
       <h1 className="text-3xl font-bold text-gray-900">
        Vérification KYC - {verification.email}
       </h1>
       <p className="mt-2 text-sm text-gray-700">
        Détails de la vérification d'identité
       </p>
      </div>
     </div>
     <div className="flex space-x-3">
      {verification.verification_status === 'PENDING' && (
       <>
        <Button
         variant="outline"
         onClick={() => setShowRejectModal(true)}
         className="flex items-center text-red-600 border-red-300 hover:bg-red-50"
         disabled={isSubmitting}
        >
         <XCircleIcon className="h-5 w-5 mr-2" />
         Rejeter
        </Button>
        <Button
         variant="primary"
         onClick={handleApprove}
         className="flex items-center"
         disabled={isSubmitting}
        >
         <CheckCircleIcon className="h-5 w-5 mr-2" />
         Approuver
        </Button>
       </>
      )}
     </div>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {/* Main Information */}
    <div className="lg:col-span-2 space-y-6">
     {/* User Information */}
     <div className="bg-white shadow rounded-lg p-6">
      <div className="flex items-center justify-between mb-4">
       <h2 className="text-lg font-medium text-gray-900">Informations Utilisateur</h2>
       {getStatusBadge(verification.verification_status)}
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-500">Email</label>
        <div className="mt-1 text-sm text-gray-900">{verification.email}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Type d'utilisateur</label>
        <div className="mt-1 text-sm text-gray-900">{getUserTypeLabel(verification.user_type)}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Numéro de téléphone</label>
        <div className="mt-1 text-sm text-gray-900">{verification.phone_number || 'Non renseigné'}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Date d'inscription</label>
        <div className="mt-1 text-sm text-gray-900">
         {new Date(verification.user_created_at).toLocaleDateString('fr-FR')}
        </div>
       </div>
       {verification.trade_category && (
        <div>
         <label className="block text-sm font-medium text-gray-500">Catégorie métier</label>
         <div className="mt-1 text-sm text-gray-900">{verification.trade_category}</div>
        </div>
       )}
       <div>
        <label className="block text-sm font-medium text-gray-500">Statut KYC artisan</label>
        <div className="mt-1 text-sm text-gray-900">
         {verification.is_kyc_verified ? 'Vérifié' : 'Non vérifié'}
        </div>
       </div>
      </div>
     </div>

     {/* Identity Document Information */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Pièce d'Identité</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
       <div>
        <label className="block text-sm font-medium text-gray-500">Type de pièce</label>
        <div className="mt-1 text-sm text-gray-900">{getIdTypeLabel(verification.id_type)}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Numéro de pièce</label>
        <div className="mt-1 text-sm text-gray-900 font-mono">{verification.id_number}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Date de soumission</label>
        <div className="mt-1 text-sm text-gray-900">
         {new Date(verification.created_at).toLocaleDateString('fr-FR')} à{' '}
         {new Date(verification.created_at).toLocaleTimeString('fr-FR')}
        </div>
       </div>
       {verification.verified_at && (
        <div>
         <label className="block text-sm font-medium text-gray-500">Date de vérification</label>
         <div className="mt-1 text-sm text-gray-900">
          {new Date(verification.verified_at).toLocaleDateString('fr-FR')} à{' '}
          {new Date(verification.verified_at).toLocaleTimeString('fr-FR')}
         </div>
        </div>
       )}
      </div>

      {/* Document Images */}
      {(verification.id_front_image_path || verification.id_back_image_path || verification.selfie_image_path) && (
       <div className="mt-6">
        <h3 className="text-md font-medium text-gray-900 mb-3">Documents soumis</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
         {verification.id_front_image_path && (
          <div>
           <label className="block text-sm font-medium text-gray-500 mb-2">Recto</label>
           <img
            src={`/storage/${verification.id_front_image_path}`}
            alt="Recto pièce d'identité"
            className="w-full h-32 object-cover rounded-lg border border-gray-300"
           />
          </div>
         )}
         {verification.id_back_image_path && (
          <div>
           <label className="block text-sm font-medium text-gray-500 mb-2">Verso</label>
           <img
            src={`/storage/${verification.id_back_image_path}`}
            alt="Verso pièce d'identité"
            className="w-full h-32 object-cover rounded-lg border border-gray-300"
           />
          </div>
         )}
         {verification.selfie_image_path && (
          <div>
           <label className="block text-sm font-medium text-gray-500 mb-2">Selfie</label>
           <img
            src={`/storage/${verification.selfie_image_path}`}
            alt="Selfie de vérification"
            className="w-full h-32 object-cover rounded-lg border border-gray-300"
           />
          </div>
         )}
        </div>
       </div>
      )}

      {/* Rejection Reason */}
      {verification.verification_status === 'REJECTED' && verification.rejection_reason && (
       <div className="mt-6 p-4 bg-red-50 rounded-lg">
        <h3 className="text-md font-medium text-red-900 mb-2">Raison du rejet</h3>
        <p className="text-sm text-red-700">{verification.rejection_reason}</p>
       </div>
      )}
     </div>
    </div>

    {/* Sidebar */}
    <div className="space-y-6">
     {/* Quick Actions */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Actions rapides</h2>
      <div className="space-y-3">
       <Link
        href={`/backoffice/users/${verification.user_id}`}
        className="flex items-center text-blue-600 hover:text-blue-800"
       >
        <UserIcon className="h-5 w-5 mr-2" />
        Voir le profil utilisateur
       </Link>
       <Link
        href="/backoffice/kyc/pending"
        className="flex items-center text-gray-600 hover:text-gray-800"
       >
        <ClockIcon className="h-5 w-5 mr-2" />
        Autres vérifications en attente
       </Link>
      </div>
     </div>

     {/* History */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Historique</h2>
      <div className="space-y-3">
       {history.map((entry, index) => (
        <div key={index} className="flex items-start space-x-3">
         <div className="flex-shrink-0">
          <CalendarIcon className="h-5 w-5 text-gray-400" />
         </div>
         <div className="flex-1 min-w-0">
          <div className="text-sm font-medium text-gray-900">
           {getActionLabel(entry.action)}
          </div>
          <div className="text-xs text-gray-500">
           {new Date(entry.created_at).toLocaleDateString('fr-FR')} à{' '}
           {new Date(entry.created_at).toLocaleTimeString('fr-FR')}
          </div>
          {entry.details && (
           <div className="text-xs text-gray-600 mt-1">
            {JSON.parse(entry.details).reason && (
             <span>Raison: {JSON.parse(entry.details).reason}</span>
            )}
           </div>
          )}
         </div>
        </div>
       ))}
      </div>
     </div>
    </div>
   </div>

   {/* Reject Modal */}
   <Modal
    isOpen={showRejectModal}
    onClose={() => setShowRejectModal(false)}
    title="Rejeter la vérification KYC"
    maxWidth="max-w-md"
   >
    <div className="space-y-4">
     <div>
      <label className="block text-sm font-medium text-gray-700 mb-2">
       Raison du rejet *
      </label>
      <textarea
       className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       rows={4}
       placeholder="Expliquez pourquoi cette vérification est rejetée..."
       value={rejectReason}
       onChange={(e) => setRejectReason(e.target.value)}
      />
     </div>
     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowRejectModal(false)}
       disabled={isSubmitting}
      >
       Annuler
      </Button>
      <Button
       variant="primary"
       onClick={handleReject}
       disabled={!rejectReason.trim() || isSubmitting}
       className="bg-red-600 hover:bg-red-700"
      >
       <XCircleIcon className="h-4 w-4 mr-2" />
       Rejeter
      </Button>
     </div>
    </div>
   </Modal>
  </BackofficeLayout>
 );
}
