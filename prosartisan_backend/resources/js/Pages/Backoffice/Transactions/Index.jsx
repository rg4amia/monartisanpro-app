import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    EyeIcon,
    DocumentArrowDownIcon,
    CurrencyDollarIcon,
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function TransactionsIndex({ transactions, filters, stats, transactionTypes, transactionStatuses }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showFilters, setShowFilters] = useState(false);
    const [showExportModal, setShowExportModal] = useState(false);
    const [exportForm, setExportForm] = useState({
        date_from: '',
        date_to: ''
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/backoffice/transactions', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key];
        router.get('/backoffice/transactions', newFilters, { preserveState: true });
    };

    const clearFilters = () => {
        router.get('/backoffice/transactions', {}, { preserveState: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams(exportForm);
        window.open(`/backoffice/transactions/export?${params}`, '_blank');
        setShowExportModal(false);
    };

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
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
                <Icon className="w-3 h-3 mr-1" />
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

    return (
        <BackofficeLayout>
            <Head title="Gestion des Transactions" />

            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Gestion des Transactions</h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Gérez toutes les transactions de la plateforme
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <Button
                            variant="outline"
                            onClick={() => setShowExportModal(true)}
                            className="flex items-center"
                        >
                            <DocumentArrowDownIcon className="h-5 w-5 mr-2" />
                            Exporter
                        </Button>
                        <Link href="/backoffice/transactions/jetons/index">
                            <Button variant="outline">Jetons Matériel</Button>
                        </Link>
                        <Link href="/backoffice/transactions/sequestres/index">
                            <Button variant="outline">Séquestres</Button>
                        </Link>
                    </div>
                </div>
            </div>

            {/* Statistics Cards */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-6 mb-8">
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="p-5">
                        <div className="flex items-center">
                            <div className="flex-shrink-0">
                                <CurrencyDollarIcon className="h-6 w-6 text-blue-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Total Transactions
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.total_transactions}
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
                                        Terminées
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.completed_transactions}
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
                                <ClockIcon className="h-6 w-6 text-yellow-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        En attente
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.pending_transactions}
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
                                <XCircleIcon className="h-6 w-6 text-red-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Échouées
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.failed_transactions}
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
                                <CurrencyDollarIcon className="h-6 w-6 text-purple-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Volume Total
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {formatAmount(stats.total_volume)}
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
                                <CurrencyDollarIcon className="h-6 w-6 text-green-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Aujourd'hui
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {formatAmount(stats.today_volume)}
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
                                placeholder="Rechercher par email ou référence..."
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
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Type
                                </label>
                                <select
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                    value={filters.type || ''}
                                    onChange={(e) => handleFilter('type', e.target.value)}
                                >
                                    <option value="">Tous les types</option>
                                    {transactionTypes.map((type) => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                    {transactionStatuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Date début
                                </label>
                                <input
                                    type="date"
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                    value={filters.date_from || ''}
                                    onChange={(e) => handleFilter('date_from', e.target.value)}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Date fin
                                </label>
                                <input
                                    type="date"
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                    value={filters.date_to || ''}
                                    onChange={(e) => handleFilter('date_to', e.target.value)}
                                />
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

            {/* Transactions Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Transaction
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    De / Vers
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Montant
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Statut
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
                            {transactions.data.map((transaction) => (
                                <tr key={transaction.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4">
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">
                                                {transaction.id.slice(0, 8)}...
                                            </div>
                                            {transaction.mobile_money_reference && (
                                                <div className="text-sm text-gray-500">
                                                    Réf: {transaction.mobile_money_reference}
                                                </div>
                                            )}
                                            {transaction.description && (
                                                <div className="text-xs text-gray-400 truncate max-w-xs">
                                                    {transaction.description}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="text-sm text-gray-900">
                                            <div>De: {transaction.from_email || 'Externe'}</div>
                                            <div>Vers: {transaction.to_email || 'Externe'}</div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="text-sm font-medium text-gray-900">
                                            {formatAmount(transaction.amount_centimes)}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {getTypeBadge(transaction.type)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {getStatusBadge(transaction.status)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {new Date(transaction.created_at).toLocaleDateString('fr-FR')}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            href={`/backoffice/transactions/${transaction.id}`}
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
                {transactions.links && (
                    <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div className="flex items-center justify-between">
                            <div className="flex-1 flex justify-between sm:hidden">
                                {transactions.prev_page_url && (
                                    <Link
                                        href={transactions.prev_page_url}
                                        className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Précédent
                                    </Link>
                                )}
                                {transactions.next_page_url && (
                                    <Link
                                        href={transactions.next_page_url}
                                        className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Suivant
                                    </Link>
                                )}
                            </div>
                            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm text-gray-700">
                                        Affichage de <span className="font-medium">{transactions.from}</span> à{' '}
                                        <span className="font-medium">{transactions.to}</span> sur{' '}
                                        <span className="font-medium">{transactions.total}</span> résultats
                                    </p>
                                </div>
                                <div>
                                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        {transactions.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                    } ${index === 0 ? 'rounded-l-md' : ''} ${index === transactions.links.length - 1 ? 'rounded-r-md' : ''
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

            {/* Export Modal */}
            <Modal
                isOpen={showExportModal}
                onClose={() => setShowExportModal(false)}
                title="Exporter les transactions"
                maxWidth="max-w-md"
            >
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Date de début
                        </label>
                        <input
                            type="date"
                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                            value={exportForm.date_from}
                            onChange={(e) => setExportForm({ ...exportForm, date_from: e.target.value })}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Date de fin
                        </label>
                        <input
                            type="date"
                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                            value={exportForm.date_to}
                            onChange={(e) => setExportForm({ ...exportForm, date_to: e.target.value })}
                        />
                    </div>
                    <div className="flex justify-end space-x-3">
                        <Button
                            variant="outline"
                            onClick={() => setShowExportModal(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleExport}
                        >
                            <DocumentArrowDownIcon className="h-4 w-4 mr-2" />
                            Exporter CSV
                        </Button>
                    </div>
                </div>
            </Modal>
        </BackofficeLayout>
    );
}
