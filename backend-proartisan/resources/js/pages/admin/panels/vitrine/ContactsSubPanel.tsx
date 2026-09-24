import { router, useForm } from '@inertiajs/react';
import React, { useState, useMemo } from 'react';
import { cn } from '@/lib/utils';
import { useConfirm } from '../../shared';

// =============================================================================
// SUB-PANEL 9: DEMANDES DE CONTACT & GESTION DES REQUÊTES
// =============================================================================
export function ContactsSubPanel({ messages = [] }: { messages: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
    const [selectedMessage, setSelectedMessage] = useState<any | null>(null);
    const [statusFilter, setStatusFilter] = useState<'all' | 'nouveau' | 'en_cours' | 'traite' | 'archive'>('all');
    const [searchQuery, setSearchQuery] = useState('');

    const { data, setData, post, processing } = useForm({
        statut: 'nouveau' as 'nouveau' | 'en_cours' | 'traite' | 'archive',
        priorite: 'normale' as 'basse' | 'normale' | 'urgente',
        notes_admin: '',
        reponse_envoyee: '',
    });

    const openManage = (msg: any) => {
        setSelectedMessage(msg);
        setData({
            statut: msg.statut || 'nouveau',
            priorite: msg.priorite || 'normale',
            notes_admin: msg.notes_admin || '',
            reponse_envoyee: msg.reponse_envoyee || '',
        });
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedMessage) return;

        post(`/admin/vitrine/contacts/${selectedMessage.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedMessage(null);
            },
            onError: (errs) => {
                console.error('Erreur mise à jour contact:', errs);
            }
        });
    };



    const handleQuickStatus = (msg: any, newStatus: string) => {
        router.post(`/admin/vitrine/contacts/${msg.id}`, {
            statut: newStatus,
            priorite: msg.priorite || 'normale',
            notes_admin: msg.notes_admin || '',
        }, { preserveScroll: true });
    };

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: 'Supprimer la demande de contact',
            message: 'Supprimer définitivement cette demande de contact ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/contacts/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedMessage?.id === id) {
                    setSelectedMessage(null);
                }
            }
        });
    };

    // KPIs
    const stats = useMemo(() => {
        return {
            total: messages.length,
            nouveau: messages.filter(m => m.statut === 'nouveau').length,
            en_cours: messages.filter(m => m.statut === 'en_cours').length,
            traite: messages.filter(m => m.statut === 'traite').length,
            archive: messages.filter(m => m.statut === 'archive').length,
        };
    }, [messages]);

    // Filtering
    const filteredMessages = useMemo(() => {
        return messages.filter(msg => {
            const matchesStatus = statusFilter === 'all' || msg.statut === statusFilter;
            const q = searchQuery.toLowerCase().trim();
            const matchesSearch = !q || (
                (msg.nom || '').toLowerCase().includes(q) ||
                (msg.email || '').toLowerCase().includes(q) ||
                (msg.telephone || '').toLowerCase().includes(q) ||
                (msg.sujet || '').toLowerCase().includes(q) ||
                (msg.message || '').toLowerCase().includes(q) ||
                (msg.artisan?.name || '').toLowerCase().includes(q)
            );
            return matchesStatus && matchesSearch;
        });
    }, [messages, statusFilter, searchQuery]);

    const getStatusBadge = (statut: string) => {
        switch (statut) {
            case 'nouveau':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-rose-100 text-rose-800 border border-rose-300 animate-pulse">
                        <span className="h-1.5 w-1.5 rounded-full bg-rose-600"></span>
                        Nouveau
                    </span>
                );
            case 'en_cours':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-amber-100 text-amber-800 border border-amber-300">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                        En cours
                    </span>
                );
            case 'traite':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                        Traité
                    </span>
                );
            case 'archive':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-medium bg-stone-100 text-stone-600 border border-stone-300">
                        Archivé
                    </span>
                );
            default:
                return null;
        }
    };

    const getPriorityBadge = (p: string) => {
        switch (p) {
            case 'urgente':
                return <span className="text-2xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 border border-red-300">🔥 Urgente</span>;
            case 'basse':
                return <span className="text-2xs font-medium px-2 py-0.5 rounded bg-stone-100 text-stone-600">Basse</span>;
            default:
                return <span className="text-2xs font-medium px-2 py-0.5 rounded bg-blue-50 text-blue-700">Normale</span>;
        }
    };

    return (
        <div className="space-y-6">
            {/* Header & Stats Cards */}
            <div>
                <div className="flex items-center justify-between flex-wrap gap-2 mb-4">
                    <div>
                        <h3 className="text-lg font-bold text-[var(--admin-text)]">
                            Suivi & Gestion des Requêtes de Contact
                        </h3>
                        <p className="text-xs text-[var(--admin-text-soft)]">
                            Toutes les prises de contact et demandes d'estimation soumises depuis le formulaire du site web.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('all')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'all'
                                ? "bg-[#ebb95e]/15 border-[#ebb95e] shadow-sm"
                                : "bg-[var(--admin-panel-strong)] border-[var(--admin-border)] hover:bg-[var(--admin-panel-strong)]"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-[var(--admin-text-soft)] font-bold">Total Requêtes</p>
                        <p className="text-2xl font-extrabold text-[var(--admin-text)] mt-1">{stats.total}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('nouveau')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'nouveau'
                                ? "bg-rose-100/60 border-rose-400 shadow-sm"
                                : "bg-[var(--admin-panel-strong)] border-[var(--admin-border)] hover:brightness-110"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-rose-700 font-bold flex items-center justify-between">
                            <span>Nouveaux</span>
                            {stats.nouveau > 0 && <span className="h-2 w-2 rounded-full bg-rose-600 animate-ping"></span>}
                        </p>
                        <p className="text-2xl font-extrabold text-rose-800 mt-1">{stats.nouveau}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('en_cours')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'en_cours'
                                ? "bg-amber-100/60 border-amber-400 shadow-sm"
                                : "bg-[var(--admin-panel-strong)] border-[var(--admin-border)] hover:brightness-110"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-amber-700 font-bold">En cours</p>
                        <p className="text-2xl font-extrabold text-amber-800 mt-1">{stats.en_cours}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('traite')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'traite'
                                ? "bg-emerald-100/60 border-emerald-400 shadow-sm"
                                : "bg-[var(--admin-panel-strong)] border-[var(--admin-border)] hover:brightness-110"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-emerald-700 font-bold">Traités / Résolus</p>
                        <p className="text-2xl font-extrabold text-emerald-800 mt-1">{stats.traite}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('archive')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'archive'
                                ? "bg-stone-200/60 border-stone-400 shadow-sm"
                                : "bg-[var(--admin-panel-strong)] border-[var(--admin-border)] hover:brightness-110"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-stone-600 font-bold">Archivés</p>
                        <p className="text-2xl font-extrabold text-stone-700 mt-1">{stats.archive}</p>
                    </button>
                </div>
            </div>

            {/* Filter / Search Bar */}
            <div className="flex flex-wrap items-center justify-between gap-3 bg-[var(--admin-panel-strong)] p-3 rounded-2xl border border-[var(--admin-border)]">
                <div className="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par nom, email, téléphone, sujet ou mot-clé..."
                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs bg-[var(--admin-panel-strong)] focus:outline-none focus:border-[#ebb95e]"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-2xs font-bold text-[var(--admin-text-soft)] uppercase">Affichage :</span>
                    <select
                        value={statusFilter}
                        onChange={e => setStatusFilter(e.target.value as any)}
                        className="rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs bg-[var(--admin-panel-strong)] focus:outline-none"
                    >
                        <option value="all">Toutes les requêtes ({stats.total})</option>
                        <option value="nouveau">Nouveaux seulement ({stats.nouveau})</option>
                        <option value="en_cours">En cours ({stats.en_cours})</option>
                        <option value="traite">Traités ({stats.traite})</option>
                        <option value="archive">Archivés ({stats.archive})</option>
                    </select>
                </div>
            </div>

            {/* List / Table */}
            <div className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm">
                {filteredMessages.length === 0 ? (
                    <div className="py-12 text-center text-[var(--admin-text-soft)] space-y-2">
                        <p className="text-sm font-semibold">Aucune demande de contact ne correspond aux critères.</p>
                        <p className="text-xs">Les messages soumis depuis le formulaire du site web apparaîtront ici en temps réel.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-[var(--admin-border)]">
                        {filteredMessages.map((msg) => (
                            <div
                                key={msg.id}
                                className={cn(
                                    "p-4 transition hover:bg-[var(--admin-panel-strong)] flex flex-col md:flex-row md:items-center justify-between gap-4",
                                    msg.statut === 'nouveau' && "bg-rose-50/40 font-medium"
                                )}
                            >
                                <div className="space-y-1.5 flex-1 min-w-0">
                                    <div className="flex items-center flex-wrap gap-2">
                                        {getStatusBadge(msg.statut)}
                                        {getPriorityBadge(msg.priorite)}
                                        <span className="text-xs text-[var(--admin-text-soft)]">
                                            {new Date(msg.created_at).toLocaleDateString('fr-FR', {
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                        {msg.artisan && (
                                            <span className="text-2xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-900 border border-amber-200">
                                                🎯 Artisan ciblé : {msg.artisan.name}
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex items-baseline gap-2">
                                        <h4 className="text-sm font-bold text-[var(--admin-text)] truncate">{msg.sujet}</h4>
                                    </div>

                                    <p className="text-xs text-[var(--admin-text)] line-clamp-2 bg-[var(--admin-panel)] p-2 rounded-xl border border-[var(--admin-border)]/50">
                                        "{msg.message}"
                                    </p>

                                    <div className="flex items-center flex-wrap gap-4 text-xs text-[var(--admin-text-soft)] pt-1">
                                        <span className="font-semibold text-[var(--admin-text)]">👤 {msg.nom}</span>
                                        <a href={`mailto:${msg.email}`} className="text-blue-600 hover:underline flex items-center gap-1">
                                            ✉️ {msg.email}
                                        </a>
                                        {msg.telephone && (
                                            <a href={`tel:${msg.telephone}`} className="text-emerald-700 font-semibold hover:underline flex items-center gap-1">
                                                📞 {msg.telephone}
                                            </a>
                                        )}
                                        {msg.traite_par && (
                                            <span className="text-2xs text-stone-500">
                                                ✓ Traité par {msg.traite_par.name}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 shrink-0 self-end md:self-center">
                                    <button
                                        type="button"
                                        onClick={() => openManage(msg)}
                                        className="rounded-xl px-4 py-2 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] transition shadow-sm"
                                    >
                                        Gérer & Suivre
                                    </button>

                                    {msg.statut !== 'traite' ? (
                                        <button
                                            type="button"
                                            title="Marquer comme traité"
                                            onClick={() => handleQuickStatus(msg, 'traite')}
                                            className="rounded-xl p-2 text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200 transition"
                                        >
                                            ✓
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            title="Archiver"
                                            onClick={() => handleQuickStatus(msg, 'archive')}
                                            className="rounded-xl p-2 text-xs font-semibold bg-stone-100 text-stone-700 border border-stone-300 hover:bg-stone-200 transition"
                                        >
                                            📁
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        title="Supprimer"
                                        onClick={() => handleDelete(msg.id)}
                                        className="rounded-xl p-2 text-xs font-semibold bg-red-100 text-red-700 border border-red-300 hover:bg-red-200 transition"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Modal de Suivi & Traitement de la Requête */}
            {selectedMessage && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-2xl shadow-2xl relative my-8">
                        <div className="flex justify-between items-start pb-4 border-b border-[var(--admin-border)]">
                            <div>
                                <span className="text-2xs uppercase tracking-wider font-extrabold text-[#b77918]">
                                    Requête de Contact #{selectedMessage.id}
                                </span>
                                <h4 className="text-lg font-bold text-[var(--admin-text)]">
                                    {selectedMessage.sujet}
                                </h4>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedMessage(null)}
                                className="h-8 w-8 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-700 flex items-center justify-center font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Coordonnées Expéditeur */}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 my-4 p-4 bg-stone-50 rounded-2xl border border-stone-200 text-xs">
                            <div>
                                <p className="text-stone-500 font-medium">Expéditeur</p>
                                <p className="font-bold text-stone-900 mt-0.5">{selectedMessage.nom}</p>
                            </div>
                            <div>
                                <p className="text-stone-500 font-medium">Email</p>
                                <a href={`mailto:${selectedMessage.email}`} className="font-semibold text-blue-600 hover:underline block mt-0.5">
                                    {selectedMessage.email}
                                </a>
                            </div>
                            <div>
                                <p className="text-stone-500 font-medium">Téléphone</p>
                                <p className="font-bold text-emerald-800 mt-0.5">
                                    {selectedMessage.telephone ? (
                                        <a href={`tel:${selectedMessage.telephone}`} className="hover:underline">
                                            {selectedMessage.telephone}
                                        </a>
                                    ) : (
                                        <span className="text-stone-400 font-normal">Non renseigné</span>
                                    )}
                                </p>
                            </div>
                            <div className="sm:col-span-3 flex items-center justify-between text-2xs text-stone-500 pt-2 border-t border-stone-200">
                                <span>Reçu le : {new Date(selectedMessage.created_at).toLocaleString('fr-FR')}</span>
                                {selectedMessage.ip_address && <span>IP : {selectedMessage.ip_address}</span>}
                            </div>
                        </div>

                        {/* Artisan ciblé si applicable */}
                        {selectedMessage.artisan && (
                            <div className="mb-4 p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs flex items-center justify-between">
                                <div>
                                    <p className="text-amber-800 font-bold">🎯 Demande associée à l'artisan :</p>
                                    <p className="text-amber-950 font-semibold">{selectedMessage.artisan.name} ({selectedMessage.artisan.phone})</p>
                                </div>
                            </div>
                        )}

                        {/* Message original */}
                        <div className="mb-6 space-y-1.5">
                            <label className="block text-xs font-bold text-[var(--admin-text)] uppercase tracking-wider">
                                Message de l'utilisateur :
                            </label>
                            <div className="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/80 text-xs text-[#241b16] whitespace-pre-wrap leading-relaxed">
                                {selectedMessage.message}
                            </div>
                        </div>

                        {/* Formulaire de Suivi */}
                        <form onSubmit={handleUpdate} className="space-y-4 pt-4 border-t border-[var(--admin-border)]">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                        Statut de la Requête *
                                    </label>
                                    <select
                                        value={data.statut}
                                        onChange={e => setData('statut', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold focus:outline-none"
                                    >
                                        <option value="nouveau">🔴 Nouveau (À traiter)</option>
                                        <option value="en_cours">🟡 En cours de traitement</option>
                                        <option value="traite">🟢 Traité / Résolu</option>
                                        <option value="archive">📁 Archivé</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                        Niveau de Priorité
                                    </label>
                                    <select
                                        value={data.priorite}
                                        onChange={e => setData('priorite', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs focus:outline-none"
                                    >
                                        <option value="normale">Normale</option>
                                        <option value="urgente">🔥 Urgente</option>
                                        <option value="basse">Basse</option>
                                    </select>
                                </div>
                            </div>

                            {/* Notes internes de suivi */}
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                    Notes internes de suivi & compte-rendu (Visible uniquement par l'équipe admin)
                                </label>
                                <textarea
                                    value={data.notes_admin}
                                    onChange={e => setData('notes_admin', e.target.value)}
                                    placeholder="Ex: Client rappelé par téléphone le 28/08 à 14h. Rendez-vous fixé avec l'artisan Kouamé..."
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs h-20 focus:outline-none"
                                />
                            </div>

                            {/* Réponse envoyée */}
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                    Réponse officielle enregistrée / apportée
                                </label>
                                <textarea
                                    value={data.reponse_envoyee}
                                    onChange={e => setData('reponse_envoyee', e.target.value)}
                                    placeholder="Copie du message ou email de réponse adressé au client..."
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs h-20 focus:outline-none"
                                />
                            </div>

                            {selectedMessage.traite_at && (
                                <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-2xs text-emerald-800 flex items-center justify-between">
                                    <span>Traité le : {new Date(selectedMessage.traite_at).toLocaleString('fr-FR')}</span>
                                    {selectedMessage.traite_par && <span>Par : {selectedMessage.traite_par.name}</span>}
                                </div>
                            )}

                            <div className="flex justify-between items-center pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => handleDelete(selectedMessage.id)}
                                    className="rounded-xl px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                                >
                                    Supprimer la requête
                                </button>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedMessage(null)}
                                        className="rounded-xl px-4 py-2 text-xs font-semibold border border-[var(--admin-border)] text-stone-700 hover:bg-stone-50"
                                    >
                                        Fermer
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-xl px-5 py-2 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] shadow-sm disabled:opacity-50"
                                    >
                                        {processing ? 'Enregistrement...' : 'Enregistrer le Suivi'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            {confirmDialog}
        </div>
    );
}
