import { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  LockClosedIcon,
  CheckCircleIcon,
  ArrowPathIcon,
  XCircleIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';

export default function TransactionsSequestres({ sequestres, filters, stats, sequestreStatuses }) {
  const [search, setSearch] = useState(filters.search || '');
  const [showFilters, setShowFilters] = useState(false);

  const handleSearch = (e) => {
    e.preventDefault();
    router.get('/backoffice/transactions/sequestres/index', { ...filters, search }, { preserveState: true });
  };

  const handleFilter = (key, value) => {
    const newFilters = { ...filters, [key]: value };
    if (!value) delete newFilters[key];
    router.get('/backoffice/transactions/sequestres/index', newFilters, { preserveState: true });
  };

  const clearFilters = () => {
    router.get('/backoffice/transactions/sequestres/index', {}, { preserveState: true });
  };

  const getStatusBadge = (status) => {
    const badges = {
      BLOCKED: 'bg-yellow-100 text-yellow-800',
      RELEASED: 'bg-green-100 text-green-800',
      REFUNDED: 'bg-blue-100 text-blue-800',
      FROZEN: 'bg-red-100 text-red-800',
    };

    const labels = {
      BLOCKED: 'Bloqué',
      RELEASED: 'Libéré',
      REFUNDED: 'Remboursé',
      FROZEN: 'Gelé',
    };

    const icons = {
      BLOCKED: LockClosedIcon,
      RELEASED: CheckCircleIcon,
      REFUNDED: ArrowPathIcon,
      FROZEN: XCircleIcon,
    };

    const Icon = icons[status];

    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
        <Icon className="w-3 h-3 mr-1" />
        {labels[status]}
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
      <Head title="Gestion des Séquestres" />

      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Gestion des Séquestres</h1>
            <p className="mt-2 text-sm text-gray-700">
              Gérez tous les séquestres de la plateforme
            </p>
          </div>
          <div className="flex space-x-3">
            <Link href="/backoffice/transactions">
              <Button variant="outline">Toutes les transactions</Button>
            </Link>
            <Link href="/backoffice/transactions/jetons/index">
              <Button variant="outline">Jetons Matériel</Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-7 mb-8">
        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <LockClosedIcon className="h-6 w-6 text-blue-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Total Séquestres
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {stats.total_sequestres}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <LockClosedIcon className="h-6 w-6 text-yellow-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Bloqués
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {stats.blocked_sequestres}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <CheckCircleIcon className="h-6 w-6 text-green-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Libérés
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {stats.released_sequestres}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <ArrowPathIcon className="h-6 w-6 text-blue-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Remboursés
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {stats.refunded_sequestres}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <LockClosedIcon className="h-6 w-6 text-purple-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Montant Total
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {formatAmount(stats.total_amount)}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <CheckCircleIcon className="h-6 w-6 text-green-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Matériel Libéré
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {formatAmount(stats.materials_released)}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white overflow-hidden shadow rounded-lg">
          <div className="p-5">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <CheckCircleIcon className="h-6 w-6 text-blue-600" />
              </div>
              <div className="ml-5 w-0 flex-1">
                <dl>
                  <dt className="text-sm font-medium text-gray-500 truncate">
                    Main d'œuvre Libérée
                  </dt>
                  <dd className="text-lg font-semibold text-gray-900">
                    {formatAmount(stats.labor_released)}
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Search and Filters */}
      <div className="bg-white shadow rounded-lg p-6 mb-6">
        <div className="flex flex-col sm:flex-row gap-4">
          <form onSubmit={handleSearch} className="flex-1">
            <div className="relative">
              <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
              <input
                type="text"
                placeholder="Rechercher par mission, client ou artisan..."
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
                  {sequestreStatuses.map((status) => (
                    <option key={status.value} value={status.value}>
                      {status.label}
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

      {/* Sequestres Table */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Mission
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Client / Artisan
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Montant Total
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Matériel / Main d'œuvre
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Statut
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date création
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {sequestres.data.map((sequestre) => (
                <tr key={sequestre.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div className="text-sm font-medium text-gray-900">
                        {sequestre.mission_description || 'Mission sans description'}
                      </div>
                      <div className="text-sm text-gray-500">
                        ID: {sequestre.mission_id ? sequestre.mission_id.slice(0, 8) + '...' : 'N/A'}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div className="text-sm text-gray-900">
                        <strong>Client:</strong> {sequestre.client_email || 'Non assigné'}
                      </div>
                      <div className="text-sm text-gray-500">
                        <strong>Artisan:</strong> {sequestre.artisan_email || 'Non assigné'}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">
                      {formatAmount(sequestre.total_amount_centimes)}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div className="text-sm text-gray-900">
                        Matériel: {formatAmount(sequestre.materials_amount_centimes)}
                      </div>
                      <div className="text-sm text-gray-500">
                        Main d'œuvre: {formatAmount(sequestre.labor_amount_centimes)}
                      </div>
                      <div className="text-xs text-gray-400">
                        Libéré: {formatAmount(sequestre.materials_released_centimes + sequestre.labor_released_centimes)}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {getStatusBadge(sequestre.status)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {new Date(sequestre.created_at).toLocaleDateString('fr-FR')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {sequestres.links && (
          <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div className="flex items-center justify-between">
              <div className="flex-1 flex justify-between sm:hidden">
                {sequestres.prev_page_url && (
                  <Link
                    href={sequestres.prev_page_url}
                    className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  >
                    Précédent
                  </Link>
                )}
                {sequestres.next_page_url && (
                  <Link
                    href={sequestres.next_page_url}
                    className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                  >
                    Suivant
                  </Link>
                )}
              </div>
              <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p className="text-sm text-gray-700">
                    Affichage de <span className="font-medium">{sequestres.from}</span> à{' '}
                    <span className="font-medium">{sequestres.to}</span> sur{' '}
                    <span className="font-medium">{sequestres.total}</span> résultats
                  </p>
                </div>
                <div>
                  <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    {sequestres.links.map((link, index) => (
                      <Link
                        key={index}
                        href={link.url || '#'}
                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                          ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                          } ${index === 0 ? 'rounded-l-md' : ''} ${index === sequestres.links.length - 1 ? 'rounded-r-md' : ''
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
