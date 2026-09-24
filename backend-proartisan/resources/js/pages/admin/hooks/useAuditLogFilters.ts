import { router } from '@inertiajs/react';
import { useState } from 'react';
import type React from 'react';

const AUDIT_ONLY = { only: ['auditLogs', 'auditActions', 'auditAdmins'], preserveState: true, preserveScroll: true };

/** Filtres serveur du journal d'audit (Règle d'or 17), appliqués par rechargement partiel. */
export function useAuditLogFilters() {
    const params = new URLSearchParams(window.location.search);
    const [search, setSearch] = useState(params.get('search_audit') || '');
    const [action, setAction] = useState(params.get('action_audit') || '');
    const [admin, setAdmin] = useState(params.get('admin_audit') || '');
    const [dateFrom, setDateFrom] = useState(params.get('date_from_audit') || '');
    const [dateTo, setDateTo] = useState(params.get('date_to_audit') || '');

    const apply = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/audit-logs',
            { search_audit: search, action_audit: action, admin_audit: admin, date_from_audit: dateFrom, date_to_audit: dateTo },
            AUDIT_ONLY,
        );
    };

    const reset = () => {
        setSearch('');
        setAction('');
        setAdmin('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/audit-logs', {}, AUDIT_ONLY);
    };

    return { search, setSearch, action, setAction, admin, setAdmin, dateFrom, setDateFrom, dateTo, setDateTo, apply, reset };
}
