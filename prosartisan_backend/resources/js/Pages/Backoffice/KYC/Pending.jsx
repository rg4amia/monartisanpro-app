import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 MagnifyingGlassIcon,
 EyeIcon,
 CheckCircleIcon,
 XCircleIcon,
 ClockIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';

export default function KYCPending({ verifications, filters }) {
 const [search, setSearch] = useState(filters.search || '');
 const [selectedVerifications, setSelectedVerifications] = useState([]);

 const handleSearch = (e) => {
  e.preventDefault();
  router.get('/backoffice/kyc/pending', { ...filters, search }, { preserveState: true });
 };

 const handleSelectAll = (e) => {
  if (e.target.checked) {
   setSelectedVerifications(verifications.data.map(v => v.id));
  } else {
   setSelectedVerifications([]);
  }
 };

 const handleSelectVerification = (verificationId) => {
  setSelectedVerifications(prev => {
   if (prev.includes(verificationId)) {
    return prev.filter(id => id !== verificationId);
   } else {
    return [...prev, verificationId];
   }
  });
 };

 const handleBulkApprove = () => {
  if (selectedVerifications.length === 0) return;

  router.post('/backoffice/kyc/bulk-approve', {
   verification_ids: selectedVerifications
  }, {
   onSuccess: () => {
    setSelectedVerifications([]);
   }
  });
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
   CNI: 'CNI',
   PASSPORT: 'Passeport',
   DRIVING_LICENSE: 'Permis',
  };
  return labels[type] || type;
 };

 return (
  <BackofficeLayout>
   <Head title="KYC - En Attente" />

   <div className="mb-8">
    <div className="flex items-center justify-between">
     <div>
      <h1 className="text-3xl font-bold text-gray-900">Vérifications KYC en Attente</h1>
      <p className="mt-2 text-sm text-gray-700">
       Gérez les vérifications d'identité en attente de traitement
      </p>
     </div>
     <div className="flex space-x-3">
      <Link href="/backoffice/kyc">
       <Button variant="outline">Toutes les vérifications</Button>
      </Link>
      {selectedVerifications.length > 0 && (
       <Button
        variant="primary"
        onClick={handleBulkApprove}
        className="flex items-center"
       >
        <CheckCircleIcon className="h-5 w-5 mr-2" />
        Approuver ({selectedVerifications.length})
       </Button>
      )}
     </div>
    </div>
   </div>

   {/* Search */}
   <div className="bg-white shadow rounded-lg p-6 mb-6">
    <form onSubmit={handleSearch} className="flex gap-4">
     <div className="flex-1 relative">
      <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
      <input
       type="text"
       placeholder="Rechercher par email ou numéro de pièce..."
       className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
       value={search}
       onChange={(e) => setSearch(e.target.value)}
      />
     </div>
     <Button type="submit" variant="primary">
      Rechercher
     </Button>
    </form>
   </div>

   {/* Verifications Table */}
   <div className="bg-white shadow rounded-lg overflow-hidden">
    <div className="overflow-x-auto">
     <table className="min-w-full divide-y divide-gray-200">
      <thead className="bg-gray-50">
       <tr>
        <th className="px-6 py-3 text-left">
         <input
          type="checkbox"
          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          onChange={handleSelectAll}
          checked={selectedVerifications.length === verifications.data.length && verifications.data.length > 0}
         />
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Utilisateur
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Type
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Pièce d'identité
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Date soumission
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
         Priorité
        </th>
        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
         Actions
        </th>
       </tr>
      </thead>
      <tbody className="bg-white divide-y divide-gray-200">
       {verifications.data.map((verification) => {
        const daysSinceSubmission = Math.floor((new Date() - new Date(verification.created_at)) / (1000 * 60 * 60 * 24));
        const isUrgent = daysSinceSubmission > 3;

        return (
         <tr key={verification.id} className={`hover:bg-gray-50 ${isUrgent ? 'bg-red-50' : ''}`}>
          <td className="px-6 py-4 whitespace-nowrap">
           <input
            type="checkbox"
            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            checked={selectedVerifications.includes(verification.id)}
            onChange={() => handleSelectVerification(verification.id)}
           />
          </td>
          <td className="px-6 py-4 whitespace-nowrap">
           <div>
            <div className="text-sm font-medium text-gray-900">
             {verification.email}
            </div>
            {verification.trade_category && (
             <div className="text-sm text-gray-500">
              {verification.trade_category}
             </div>
            )}
           </div>
          </td>
          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
           {getUserTypeLabel(verification.user_type)}
          </td>
          <td className="px-6 py-4 whitespace-nowrap">
           <div>
            <div className="text-sm font-medium text-gray-900">
             {getIdTypeLabel(verification.id_type)}
            </div>
            <div className="text-sm text-gray-500">
             {verification.id_number}
            </div>
           </div>
          </td>
          <td className="px-6 py-4 whitespace-nowrap">
           <div className="text-sm text-gray-900">
            {new Date(verification.created_at).toLocaleDateString('fr-FR')}
           </div>
           <div className="text-xs text-gray-500">
            Il y a {daysSinceSubmission} jour{daysSinceSubmission > 1 ? 's' : ''}
           </div>
          </td>
          <td className="px-6 py-4 whitespace-nowrap">
           {isUrgent ? (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
             <ClockIcon className="w-3 h-3 mr-1" />
             Urgent
            </span>
           ) : (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
             <ClockIcon className="w-3 h-3 mr-1" />
             Normal
            </span>
           )}
          </td>
          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
           <div className="flex items-center justify-end space-x-2">
            <Link
             href={`/backoffice/kyc/${verification.id}`}
             className="text-blue-600 hover:text-blue-900 inline-flex items-center"
            >
             <EyeIcon className="h-4 w-4 mr-1" />
             Examiner
            </Link>
           </div>
          </td>
         </tr>
        );
       })}
      </tbody>
     </table>
    </div>

    {/* Pagination */}
    {verifications.links && (
     <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
      <div className="flex items-center justify-between">
       <div className="flex-1 flex justify-between sm:hidden">
        {verifications.prev_page_url && (
         <Link
          href={verifications.prev_page_url}
          className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Précédent
         </Link>
        )}
        {verifications.next_page_url && (
         <Link
          href={verifications.next_page_url}
          className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
         >
          Suivant
         </Link>
        )}
       </div>
       <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
         <p className="text-sm text-gray-700">
          Affichage de <span className="font-medium">{verifications.from}</span> à{' '}
          <span className="font-medium">{verifications.to}</span> sur{' '}
          <span className="font-medium">{verifications.total}</span> résultats
         </p>
        </div>
        <div>
         <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
          {verifications.links.map((link, index) => (
           <Link
            key={index}
            href={link.url || '#'}
            className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
             ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
             : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
             } ${index === 0 ? 'rounded-l-md' : ''} ${index === verifications.links.length - 1 ? 'rounded-r-md' : ''
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

   {verifications.data.length === 0 && (
    <div className="bg-white shadow rounded-lg p-6 text-center">
     <ClockIcon className="mx-auto h-12 w-12 text-gray-400" />
     <h3 className="mt-2 text-sm font-medium text-gray-900">Aucune vérification en attente</h3>
     <p className="mt-1 text-sm text-gray-500">
      Toutes les vérifications KYC ont été traitées.
     </p>
    </div>
   )}
  </BackofficeLayout>
 );
}
