import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type React from 'react';
import type { AdminPageProps } from '../shared';

type Communication = NonNullable<AdminPageProps['communications']>[number];

const EMPTY_COMMUNICATION = {
    type: 'annonce',
    titre: '',
    contenu: '',
    cibles: [] as string[],
    // Contenu vocal : fichier téléversé. Contenu vidéo : lien externe.
    media_file: null as File | null,
    media_external_url: '',
    media_duration: '',
};

/** Création/édition des communications et filtrage local de la liste (type, statut, recherche). */
export function useCommunicationForm(communications: AdminPageProps['communications'], deferredSearch: string) {
    const [commModalOpen, setCommModalOpen] = useState<boolean>(false);
    const [editingComm, setEditingComm] = useState<any>(null);
    const [commTypeFilter, setCommTypeFilter] = useState<string>('all');
    const [commStatusFilter, setCommStatusFilter] = useState<string>('all');
    const commForm = useForm({ ...EMPTY_COMMUNICATION });

    const filteredCommunications = useMemo(() => {
        if (!communications) return [];
        return communications.filter((comm: any) => {
            const matchesSearch = !deferredSearch ||
                comm.titre.toLowerCase().includes(deferredSearch) ||
                comm.contenu.toLowerCase().includes(deferredSearch);
            const matchesType = commTypeFilter === 'all' || comm.type === commTypeFilter;
            const matchesStatus = commStatusFilter === 'all' || comm.statut === commStatusFilter;
            return matchesSearch && matchesType && matchesStatus;
        });
    }, [communications, deferredSearch, commTypeFilter, commStatusFilter]);

    const openCreateCommModal = (): void => {
        setEditingComm(null);
        commForm.reset();
        commForm.clearErrors();
        commForm.setData({ ...EMPTY_COMMUNICATION, cibles: [] });
        setCommModalOpen(true);
    };

    const openEditCommModal = (comm: Communication | any): void => {
        setEditingComm(comm);
        commForm.clearErrors();
        commForm.setData({
            type: comm.type,
            titre: comm.titre,
            contenu: comm.contenu,
            cibles: comm.cibles_json,
            // Le fichier déjà téléversé n'est pas renvoyé : le laisser vide
            // conserve l'existant, en choisir un nouveau le remplace.
            media_file: null,
            media_external_url: comm.media_external_url ?? '',
            media_duration: comm.media_duration ? String(comm.media_duration) : '',
        });
        setCommModalOpen(true);
    };

    const submitCommForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (commForm.processing) return;
        const onSuccess = (): void => {
            setCommModalOpen(false);
            commForm.reset();
        };

        if (editingComm) {
            // Inertia ne sait pas transporter un fichier en PUT : on poste en
            // usurpant la méthode, ce qui fonctionne avec ou sans fichier.
            commForm.transform((data) => ({ ...data, _method: 'put' }));
            commForm.post(`/admin/communications/${editingComm.id}`, { preserveScroll: true, forceFormData: true, onSuccess });
        } else {
            commForm.post('/admin/communications', { preserveScroll: true, forceFormData: true, onSuccess });
        }
    };

    return {
        commForm,
        commModalOpen,
        closeCommModal: () => setCommModalOpen(false),
        editingComm,
        commTypeFilter,
        setCommTypeFilter,
        commStatusFilter,
        setCommStatusFilter,
        filteredCommunications,
        openCreateCommModal,
        openEditCommModal,
        submitCommForm,
    };
}
