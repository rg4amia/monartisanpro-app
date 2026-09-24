import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type React from 'react';
import type { AskConfirm, CampagneParrainageItem } from '../shared';

const EMPTY_CAMPAGNE = {
    libelle: '',
    discount_type: 'percent' as 'percent' | 'fixed',
    discount_value: 10,
    max_discount_amount: 0,
    min_montant: 0,
    starts_at: '',
    expires_at: '',
    is_active: true,
};

/** Création, édition, activation et suppression des campagnes de parrainage client. */
export function useCampagneParrainageForm(askConfirm: AskConfirm) {
    const [campagneModalOpen, setCampagneModalOpen] = useState<boolean>(false);
    const [editingCampagne, setEditingCampagne] = useState<CampagneParrainageItem | null>(null);
    const campagneForm = useForm({ ...EMPTY_CAMPAGNE });

    const openCreateCampagneModal = (): void => {
        setEditingCampagne(null);
        campagneForm.reset();
        campagneForm.clearErrors();
        campagneForm.setData({ ...EMPTY_CAMPAGNE });
        setCampagneModalOpen(true);
    };

    const openEditCampagneModal = (campagne: CampagneParrainageItem): void => {
        setEditingCampagne(campagne);
        campagneForm.clearErrors();
        campagneForm.setData({
            libelle: campagne.libelle,
            discount_type: campagne.discount_type,
            discount_value: campagne.discount_value,
            max_discount_amount: campagne.max_discount_amount || 0,
            min_montant: campagne.min_montant || 0,
            starts_at: campagne.starts_at ? campagne.starts_at.slice(0, 10) : '',
            expires_at: campagne.expires_at ? campagne.expires_at.slice(0, 10) : '',
            is_active: campagne.is_active,
        });
        setCampagneModalOpen(true);
    };

    const submitCampagneForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (campagneForm.processing) return;

        campagneForm.transform(() => ({
            ...campagneForm.data,
            libelle: campagneForm.data.libelle.trim(),
            max_discount_amount: Number(campagneForm.data.max_discount_amount) || null,
            min_montant: Number(campagneForm.data.min_montant) || 0,
            starts_at: campagneForm.data.starts_at || null,
            expires_at: campagneForm.data.expires_at || null,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setCampagneModalOpen(false);
                campagneForm.reset();
            },
        };
        if (editingCampagne) {
            campagneForm.put(`/admin/campagnes-parrainage/${editingCampagne.id}`, options);
        } else {
            campagneForm.post('/admin/campagnes-parrainage', options);
        }
    };

    const deleteCampagne = async (campagne: CampagneParrainageItem): Promise<void> => {
        const ok = await askConfirm({
            title: 'Supprimer la campagne de parrainage',
            message: `Supprimer définitivement la campagne "${campagne.libelle}" ?`,
            tone: 'danger',
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/admin/campagnes-parrainage/${campagne.id}`, { preserveScroll: true });
    };

    const toggleCampagne = (campagne: CampagneParrainageItem): void => {
        router.post(`/admin/campagnes-parrainage/${campagne.id}/toggle`, {}, { preserveScroll: true });
    };

    return {
        campagneForm,
        campagneModalOpen,
        closeCampagneModal: () => setCampagneModalOpen(false),
        editingCampagne,
        openCreateCampagneModal,
        openEditCampagneModal,
        submitCampagneForm,
        deleteCampagne,
        toggleCampagne,
    };
}
