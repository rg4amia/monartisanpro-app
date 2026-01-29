import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    EyeIcon,
    CheckCircleIcon,
    XCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    DocumentArrowDownIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';

export default function KYCIndex({ verifications, filters, stats, verificationStatuses, idTypes }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showFilters, setShowFilters] = useState(false);
    const [showExportModal, setShowExportModal] = useState(false);
    const [exportForm, setExportForm] = useState({
        status: '',
        date_from: '',
        date_to: ''
    });

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/backoffice/kyc', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        if (!value) delete newFilters[key];
        router.get('/backoffice/kyc', newFilters, { preserveState: true });
    };

    const clearFilters = () => {
        router.get('/backoffice/kyc', {}, { preserveState: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams(exportForm);
        window.open(`/backoffice/kyc/export?${params}`, '_blank');
        setShowExportModal(false);
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
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
                <Icon className="w-3 h-3 mr-1" />
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
            CNI: 'CNI',
            PASSPORT: 'Passeport',
            DRIVING_LICENSE: 'Permis',
        };
        return labels[type] || type;
    };

    return (
        <BackofficeLayout>
            <Head title="Gestion KYC" />

            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Gestion KYC</h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Gérez les vérifications d'identité des utilisateurs
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
                        <Link href="/backoffice/kyc/pending">
                            <Button variant="primary">En attente ({stats.pending_verifications})</Button>
                        </Link>
                        <Link href="/backoffice/kyc/approved">
                            <Button variant="outline">Approuvés</Button>
                        </Link>
                        <Link href="/backoffice/kyc/rejected">
                            <Button variant="outline">Rejetés</Button>
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
                                <DocumentTextIcon className="h-6 w-6 text-blue-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Total Vérifications
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.total_verifications}
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
                                        {stats.pending_verifications}
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
                                        Approuvées
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.approved_verifications}
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
                                        Rejetées
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.rejected_verifications}
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
                                <CheckCircleIcon className="h-6 w-6 text-purple-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Taux d'approbation
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.approval_rate.toFixed(1)}%
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
                                <ClockIcon className="h-6 w-6 text-gray-600" />
                            </div>
                            <div className="ml-5 w-0 flex-1">
                                <dl>
                                    <dt className="text-sm font-medium text-gray-500 truncate">
                                        Temps moyen
                                    </dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {stats.average_processing_time.toFixed(1)}j
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
                                placeholder="Rechercher par email ou numéro de pièce..."
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
                                    {verificationStatuses.map((status) => (
                                        <option key={status.value} value={status.value}>
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Type de pièce
                                </label>
                                <select
                                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                                    value={filters.id_type || ''}
                                    onChange={(e) => handleFilter('id_type', e.target.value)}
                                >
                                    <option value="">Tous les types</option>
                                    {idTypes.map((type) => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
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

            {/* Verifications Table */}
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
                                    Pièce d'identité
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date soumission
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {verifications.data.map((verification) => (
                                <tr key={verification.id} className="hover:bg-gray-50">
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
                                        {getStatusBadge(verification.verification_status)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {new Date(verification.created_at).toLocaleDateString('fr-FR')}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            href={`/backoffice/kyc/${verification.id}`}
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

            {/* Export Modal */}
            <Modal
                isOpen={showExportModal}
                onClose={() => setShowExportModal(false)}
                title="Exporter les vérifications KYC"
                maxWidth="max-w-md"
            >
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Statut
                        </label>
                        <select
                            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
                            value={exportForm.status}
                            onChange={(e) => setExportForm({ ...exportForm, status: e.target.value })}
                        >
                            <option value="">Tous les statuts</option>
                            {verificationStatuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
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
