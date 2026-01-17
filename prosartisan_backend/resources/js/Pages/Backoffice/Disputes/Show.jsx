import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 DocumentTextIcon,
 ChatBubbleLeftRightIcon,
 ScaleIcon,
 UserIcon,
 CalendarIcon,
 CurrencyDollarIcon,
 PhotoIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function DisputeShow({ dispute, communications, evidence, decisionTypes }) {
 const [showDecisionModal, setShowDecisionModal] = useState(false);
 const [showCommunicationModal, setShowCommunicationModal] = useState(false);
 const [showEvidenceModal, setShowEvidenceModal] = useState(false);
 const [selectedEvidence, setSelectedEvidence] = useState(null);

 const [decisionForm, setDecisionForm] = useState({
  decision_type: '',
  amount_centimes: '',
  justification: ''
 });

 const [newMessage, setNewMessage] = useState('');

 const handleRenderDecision = () => {
  router.post(`/backoffice/disputes/${dispute.id}/render-decision`, decisionForm, {
   onSuccess: () => {
    setShowDecisionModal(false);
    setDecisionForm({ decision_type: '', amount_centimes: '', justification: '' });
   }
  });
 };

 const handleAddCommunication = () => {
  if (!newMessage.trim()) return;

  router.post(`/backoffice/disputes/${dispute.id}/communications`, {
   message: newMessage
  }, {
   onSuccess: () => {
    setShowCommunicationModal(false);
    setNewMessage('');
   }
  });
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

  return (
   <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badges[status]}`}>
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

 const formatAmount = (centimes) => {
  return new Intl.NumberFormat('fr-FR', {
   style: 'currency',
   currency: 'XOF',
   minimumFractionDigits: 0,
  }).format(centimes / 100);
 };

 const canRenderDecision = dispute.status === 'IN_ARBITRATION' || dispute.status === 'OPEN';

 return (
  <BackofficeLayout>
   <Head title={`Litige - ${dispute.id}`} />

   <div className="mb-8">
    <div className="flex items-center mb-4">
     <Link
      href="/backoffice/disputes"
      className="mr-4 p-2 text-gray-400 hover:text-gray-600"
     >
      <ArrowLeftIcon className="h-5 w-5" />
     </Link>
     <div>
      <h1 className="text-3xl font-bold text-gray-900">
       Litige #{dispute.id.slice(0, 8)}
      </h1>
      <p className="mt-2 text-sm text-gray-700">
       Créé le {new Date(dispute.created_at).toLocaleDateString('fr-FR')}
      </p>
     </div>
    </div>

    <div className="flex items-center space-x-4">
     {getStatusBadge(dispute.status)}
     {getTypeBadge(dispute.type)}

     {canRenderDecision && (
      <Button
       variant="primary"
       size="sm"
       onClick={() => setShowDecisionModal(true)}
      >
       <ScaleIcon className="h-4 w-4 mr-2" />
       Rendre une décision
      </Button>
     )}

     <Button
      variant="outline"
      size="sm"
      onClick={() => setShowCommunicationModal(true)}
     >
      <ChatBubbleLeftRightIcon className="h-4 w-4 mr-2" />
      Ajouter un message
     </Button>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {/* Main Content */}
    <div className="lg:col-span-2 space-y-6">
     {/* Dispute Details */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Détails du litige</h2>
      <div className="prose max-w-none">
       <p className="text-gray-700">{dispute.description}</p>
      </div>

      <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
       <div>
        <dt className="text-sm font-medium text-gray-500">Mission concernée</dt>
        <dd className="mt-1 text-sm text-gray-900">{dispute.mission_description}</dd>
       </div>
       <div>
        <dt className="text-sm font-medium text-gray-500">Montant en jeu</dt>
        <dd className="mt-1 text-sm text-gray-900">
         {dispute.total_amount_centimes ? formatAmount(dispute.total_amount_centimes) : 'N/A'}
        </dd>
       </div>
      </div>
     </div>

     {/* Evidence */}
     {evidence.length > 0 && (
      <div className="bg-white shadow rounded-lg p-6">
       <h2 className="text-lg font-medium text-gray-900 mb-4">Preuves</h2>
       <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
        {evidence.map((item, index) => (
         <div
          key={index}
          className="relative cursor-pointer group"
          onClick={() => {
           setSelectedEvidence(item);
           setShowEvidenceModal(true);
          }}
         >
          <img
           src={item}
           alt={`Preuve ${index + 1}`}
           className="w-full h-24 object-cover rounded-lg border border-gray-200 group-hover:opacity-75"
          />
          <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
           <PhotoIcon className="h-8 w-8 text-white" />
          </div>
         </div>
        ))}
       </div>
      </div>
     )}

     {/* Communications */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Communications de médiation</h2>
      {communications.length > 0 ? (
       <div className="space-y-4">
        {communications.map((comm) => (
         <div key={comm.id} className="border-l-4 border-blue-200 pl-4">
          <div className="flex items-center justify-between mb-2">
           <span className="text-sm font-medium text-gray-900">
            {comm.sender_email}
           </span>
           <span className="text-xs text-gray-500">
            {new Date(comm.created_at).toLocaleString('fr-FR')}
           </span>
          </div>
          <p className="text-sm text-gray-700">{comm.message}</p>
         </div>
        ))}
       </div>
      ) : (
       <p className="text-gray-500 text-sm">Aucune communication pour le moment.</p>
      )}
     </div>

     {/* Decision */}
     {dispute.decision_type && (
      <div className="bg-white shadow rounded-lg p-6">
       <h2 className="text-lg font-medium text-gray-900 mb-4">Décision d'arbitrage</h2>
       <div className="space-y-4">
        <div>
         <dt className="text-sm font-medium text-gray-500">Type de décision</dt>
         <dd className="mt-1 text-sm text-gray-900">
          {decisionTypes.find(t => t.value === dispute.decision_type)?.label}
         </dd>
        </div>
        {dispute.decision_amount_centimes && (
         <div>
          <dt className="text-sm font-medium text-gray-500">Montant</dt>
          <dd className="mt-1 text-sm text-gray-900">
           {formatAmount(dispute.decision_amount_centimes)}
          </dd>
         </div>
        )}
        <div>
         <dt className="text-sm font-medium text-gray-500">Justification</dt>
         <dd className="mt-1 text-sm text-gray-900">{dispute.decision_justification}</dd>
        </div>
        <div>
         <dt className="text-sm font-medium text-gray-500">Date de la décision</dt>
         <dd className="mt-1 text-sm text-gray-900">
          {new Date(dispute.resolved_at).toLocaleString('fr-FR')}
         </dd>
        </div>
       </div>
      </div>
     )}
    </div>

    {/* Sidebar */}
    <div className="space-y-6">
     {/* Parties */}
     <div className="bg-white shadowrounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Parties impliquées</h2>
      <div className="space-y-4">
       <div className="flex items-center">
        <UserIcon className="h-5 w-5 text-red-500 mr-3" />
        <div>
         <div className="text-sm font-medium text-gray-900">Plaignant</div>
         <div className="text-sm text-gray-500">{dispute.reporter_email}</div>
        </div>
       </div>
       <div className="flex items-center">
        <UserIcon className="h-5 w-5 text-blue-500 mr-3" />
        <div>
         <div className="text-sm font-medium text-gray-900">Défendeur</div>
         <div className="text-sm text-gray-500">{dispute.defendant_email}</div>
        </div>
       </div>
       {dispute.mediator_email && (
        <div className="flex items-center">
         <UserIcon className="h-5 w-5 text-green-500 mr-3" />
         <div>
          <div className="text-sm font-medium text-gray-900">Médiateur</div>
          <div className="text-sm text-gray-500">{dispute.mediator_email}</div>
         </div>
        </div>
       )}
      </div>
     </div>

     {/* Timeline */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Chronologie</h2>
      <div className="space-y-3">
       <div className="flex items-center text-sm">
        <CalendarIcon className="h-4 w-4 text-gray-400 mr-2" />
        <span className="text-gray-500">Créé le</span>
        <span className="ml-2 text-gray-900">
         {new Date(dispute.created_at).toLocaleDateString('fr-FR')}
        </span>
       </div>
       {dispute.resolved_at && (
        <div className="flex items-center text-sm">
         <CalendarIcon className="h-4 w-4 text-gray-400 mr-2" />
         <span className="text-gray-500">Résolu le</span>
         <span className="ml-2 text-gray-900">
          {new Date(dispute.resolved_at).toLocaleDateString('fr-FR')}
         </span>
        </div>
       )}
      </div>
     </div>

     {/* Sequestre Info */}
     {dispute.total_amount_centimes && (
      <div className="bg-white shadow rounded-lg p-6">
       <h2 className="text-lg font-medium text-gray-900 mb-4">Informations financières</h2>
       <div className="space-y-3">
        <div className="flex items-center justify-between">
         <span className="text-sm text-gray-500">Montant séquestré</span>
         <span className="text-sm font-medium text-gray-900">
          {formatAmount(dispute.total_amount_centimes)}
         </span>
        </div>
        <div className="flex items-center justify-between">
         <span className="text-sm text-gray-500">Statut séquestre</span>
         <span className="text-sm font-medium text-gray-900">
          {dispute.sequestre_status}
         </span>
        </div>
       </div>
      </div>
     )}
    </div>
   </div>

   {/* Render Decision Modal */}
   <Modal
    isOpen={showDecisionModal}
    onClose={() => setShowDecisionModal(false)}
    title="Rendre une décision d'arbitrage"
    maxWidth="max-w-lg"
   >
    <div className="space-y-4">
     <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
       Type de décision
      </label>
      <select
       className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       value={decisionForm.decision_type}
       onChange={(e) => setDecisionForm({ ...decisionForm, decision_type: e.target.value })}
      >
       <option value="">Sélectionner une décision</option>
       {decisionTypes.map((type) => (
        <option key={type.value} value={type.value}>
         {type.label}
        </option>
       ))}
      </select>
     </div>

     {(decisionForm.decision_type === 'PARTIAL_REFUND') && (
      <div>
       <label className="block text-sm font-medium text-gray-700 mb-1">
        Montant (en centimes)
       </label>
       <input
        type="number"
        className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
        value={decisionForm.amount_centimes}
        onChange={(e) => setDecisionForm({ ...decisionForm, amount_centimes: e.target.value })}
        placeholder="Montant en centimes"
       />
      </div>
     )}

     <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
       Justification
      </label>
      <textarea
       className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       rows={4}
       value={decisionForm.justification}
       onChange={(e) => setDecisionForm({ ...decisionForm, justification: e.target.value })}
       placeholder="Justifiez votre décision..."
      />
     </div>

     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowDecisionModal(false)}
      >
       Annuler
      </Button>
      <Button
       variant="primary"
       onClick={handleRenderDecision}
       disabled={!decisionForm.decision_type || !decisionForm.justification}
      >
       Rendre la décision
      </Button>
     </div>
    </div>
   </Modal>

   {/* Add Communication Modal */}
   <Modal
    isOpen={showCommunicationModal}
    onClose={() => setShowCommunicationModal(false)}
    title="Ajouter un message de médiation"
    maxWidth="max-w-md"
   >
    <div className="space-y-4">
     <textarea
      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
      rows={4}
      value={newMessage}
      onChange={(e) => setNewMessage(e.target.value)}
      placeholder="Votre message..."
     />
     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowCommunicationModal(false)}
      >
       Annuler
      </Button>
      <Button
       variant="primary"
       onClick={handleAddCommunication}
       disabled={!newMessage.trim()}
      >
       Envoyer
      </Button>
     </div>
    </div>
   </Modal>

   {/* Evidence Modal */}
   <Modal
    isOpen={showEvidenceModal}
    onClose={() => setShowEvidenceModal(false)}
    title="Preuve"
    maxWidth="max-w-4xl"
   >
    {selectedEvidence && (
     <div className="text-center">
      <img
       src={selectedEvidence}
       alt="Preuve"
       className="max-w-full h-auto rounded-lg"
      />
     </div>
    )}
   </Modal>
  </BackofficeLayout>
 );
}
