import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 CheckCircleIcon,
 XCircleIcon,
 ClockIcon,
 CurrencyDollarIcon,
 CalendarIcon,
 UserIcon
} from '@heroicons/react/24/outline';

export default function TransactionShow({ transaction }) {
 const getStatusBadge = (status) => {
  const badges = {
   PENDING: 'bg-yellow-100 text-yellow-800',
   COMPLETED: 'bg-green-100 text-green-800',
   FAILED: 'bg-red-100 text-red-800',
   CANCELLED: 'bg-gray-100 text-gray-800',
  };

  const labels = {
   PENDING: 'En attente',
   COMPLETED: 'Terminé',
   FAILED: 'Échoué',
   CANCELLED: 'Annulé',
  };

  const icons = {
   PENDING: ClockIcon,
   COMPLETED: CheckCircleIcon,
   FAILED: XCircleIcon,
   CANCELLED: XCircleIcon,
  };

  const Icon = icons[status];

  return (
   <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badges[status]}`}>
    <Icon className="w-4 h-4 mr-2" />
    {labels[status]}
   </span>
  );
 };

 const getTypeBadge = (type) => {
  const badges = {
   DEPOSIT: 'bg-blue-100 text-blue-800',
   WITHDRAWAL: 'bg-purple-100 text-purple-800',
   ESCROW_RELEASE: 'bg-green-100 text-green-800',
   REFUND: 'bg-orange-100 text-orange-800',
   JETON_PURCHASE: 'bg-indigo-100 text-indigo-800',
  };

  const labels = {
   DEPOSIT: 'Dépôt',
   WITHDRAWAL: 'Retrait',
   ESCROW_RELEASE: 'Libération séquestre',
   REFUND: 'Remboursement',
   JETON_PURCHASE: 'Achat jeton',
  };

  return (
   <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badges[type]}`}>
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

 return (
  <BackofficeLayout>
   <Head title={`Transaction - ${transaction.id}`} />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div className="flex items-center space-x-4">
      <Link
       href="/backoffice/transactions"
       className="flex items-center text-gray-500 hover:text-gray-700"
      >
       <ArrowLeftIcon className="h-5 w-5 mr-2" />
       Retour aux transactions
      </Link>
      <div>
       <h1 className="text-3xl font-bold text-gray-900">
        Transaction {transaction.id.slice(0, 8)}...
       </h1>
       <p className="mt-2 text-sm text-gray-700">
        Détails de la transaction
       </p>
      </div>
     </div>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {/* Main Information */}
    <div className="lg:col-span-2 space-y-6">
     {/* Transaction Overview */}
     <div className="bg-white shadow rounded-lg p-6">
      <div className="flex items-center justify-between mb-6">
       <h2 className="text-lg font-medium text-gray-900">Informations de la Transaction</h2>
       <div className="flex space-x-2">
        {getTypeBadge(transaction.type)}
        {getStatusBadge(transaction.status)}
       </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
       <div>
        <label className="block text-sm font-medium text-gray-500">ID Transaction</label>
        <div className="mt-1 text-sm text-gray-900 font-mono">{transaction.id}</div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Montant</label>
        <div className="mt-1 text-lg font-semibold text-gray-900">
         {formatAmount(transaction.amount_centimes)}
        </div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">De (Expéditeur)</label>
        <div className="mt-1 text-sm text-gray-900">
         {transaction.from_email || 'Externe/Système'}
        </div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Vers (Destinataire)</label>
        <div className="mt-1 text-sm text-gray-900">
         {transaction.to_email || 'Externe/Système'}
        </div>
       </div>
       <div>
        <label className="block text-sm font-medium text-gray-500">Date de création</label>
        <div className="mt-1 text-sm text-gray-900">
         {new Date(transaction.created_at).toLocaleDateString('fr-FR')} à{' '}
         {new Date(transaction.created_at).toLocaleTimeString('fr-FR')}
        </div>
       </div>
       {transaction.completed_at && (
        <div>
         <label className="block text-sm font-medium text-gray-500">Date de completion</label>
         <div className="mt-1 text-sm text-gray-900">
          {new Date(transaction.completed_at).toLocaleDateString('fr-FR')} à{' '}
          {new Date(transaction.completed_at).toLocaleTimeString('fr-FR')}
         </div>
        </div>
       )}
      </div>

      {transaction.description && (
       <div className="mt-6">
        <label className="block text-sm font-medium text-gray-500">Description</label>
        <div className="mt-1 text-sm text-gray-900 p-3 bg-gray-50 rounded-md">
         {transaction.description}
        </div>
       </div>
      )}
     </div>

     {/* Mobile Money Details */}
     {transaction.mobile_money_reference && (
      <div className="bg-white shadow rounded-lg p-6">
       <h2 className="text-lg font-medium text-gray-900 mb-4">Détails Mobile Money</h2>
       <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
         <label className="block text-sm font-medium text-gray-500">Référence</label>
         <div className="mt-1 text-sm text-gray-900 font-mono">
          {transaction.mobile_money_reference}
         </div>
        </div>
        {transaction.gateway && (
         <div>
          <label className="block text-sm font-medium text-gray-500">Passerelle</label>
          <div className="mt-1 text-sm text-gray-900">{transaction.gateway}</div>
         </div>
        )}
       </div>
      </div>
     )}

     {/* Metadata */}
     {transaction.metadata && (
      <div className="bg-white shadow rounded-lg p-6">
       <h2 className="text-lg font-medium text-gray-900 mb-4">Métadonnées</h2>
       <pre className="text-sm text-gray-900 bg-gray-50 p-4 rounded-md overflow-x-auto">
        {JSON.stringify(JSON.parse(transaction.metadata), null, 2)}
       </pre>
      </div>
     )}
    </div>

    {/* Sidebar */}
    <div className="space-y-6">
     {/* Quick Stats */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Résumé</h2>
      <div className="space-y-4">
       <div className="flex items-center justify-between">
        <span className="text-sm text-gray-600">Montant</span>
        <span className="text-sm font-medium text-gray-900">
         {formatAmount(transaction.amount_centimes)}
        </span>
       </div>
       <div className="flex items-center justify-between">
        <span className="text-sm text-gray-600">Type</span>
        <span className="text-sm font-medium text-gray-900">
         {transaction.type}
        </span>
       </div>
       <div className="flex items-center justify-between">
        <span className="text-sm text-gray-600">Statut</span>
        <span className="text-sm font-medium text-gray-900">
         {transaction.status}
        </span>
       </div>
      </div>
     </div>

     {/* Quick Actions */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Actions rapides</h2>
      <div className="space-y-3">
       {transaction.from_email && (
        <Link
         href={`/backoffice/users?search=${transaction.from_email}`}
         className="flex items-center text-blue-600 hover:text-blue-800"
        >
         <UserIcon className="h-5 w-5 mr-2" />
         Voir l'expéditeur
        </Link>
       )}
       {transaction.to_email && (
        <Link
         href={`/backoffice/users?search=${transaction.to_email}`}
         className="flex items-center text-blue-600 hover:text-blue-800"
        >
         <UserIcon className="h-5 w-5 mr-2" />
         Voir le destinataire
        </Link>
       )}
       <Link
        href="/backoffice/transactions"
        className="flex items-center text-gray-600 hover:text-gray-800"
       >
        <CurrencyDollarIcon className="h-5 w-5 mr-2" />
        Toutes les transactions
       </Link>
      </div>
     </div>

     {/* Timeline */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Chronologie</h2>
      <div className="space-y-3">
       <div className="flex items-start space-x-3">
        <div className="flex-shrink-0">
         <CalendarIcon className="h-5 w-5 text-gray-400" />
        </div>
        <div className="flex-1 min-w-0">
         <div className="text-sm font-medium text-gray-900">Transaction créée</div>
         <div className="text-xs text-gray-500">
          {new Date(transaction.created_at).toLocaleDateString('fr-FR')} à{' '}
          {new Date(transaction.created_at).toLocaleTimeString('fr-FR')}
         </div>
        </div>
       </div>
       {transaction.completed_at && (
        <div className="flex items-start space-x-3">
         <div className="flex-shrink-0">
          <CheckCircleIcon className="h-5 w-5 text-green-500" />
         </div>
         <div className="flex-1 min-w-0">
          <div className="text-sm font-medium text-gray-900">Transaction terminée</div>
          <div className="text-xs text-gray-500">
           {new Date(transaction.completed_at).toLocaleDateString('fr-FR')} à{' '}
           {new Date(transaction.completed_at).toLocaleTimeString('fr-FR')}
          </div>
         </div>
        </div>
       )}
      </div>
     </div>
    </div>
   </div>
  </BackofficeLayout>
 );
}
