import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type React from 'react';
import type { AiUserQuotaRow } from '../shared';

/**
 * Surcharge des quotas IA d'un utilisateur (Règle d'or 25) : un champ vide
 * renvoie `null` (retour au réglage global), `0` signifie illimité.
 */
export function useAiQuotaForm() {
    const [aiQuotaModalOpen, setAiQuotaModalOpen] = useState<boolean>(false);
    const [aiQuotaTarget, setAiQuotaTarget] = useState<AiUserQuotaRow | null>(null);
    const aiQuotaForm = useForm<{ daily_limit: string; monthly_limit: string; blocked: boolean; note: string }>({
        daily_limit: '',
        monthly_limit: '',
        blocked: false,
        note: '',
    });

    const openEditAiQuota = (row: AiUserQuotaRow): void => {
        setAiQuotaTarget(row);
        aiQuotaForm.clearErrors();
        aiQuotaForm.setData({
            daily_limit: row.override_daily === null ? '' : String(row.override_daily),
            monthly_limit: row.override_monthly === null ? '' : String(row.override_monthly),
            blocked: Boolean(row.blocked),
            note: row.note ?? '',
        });
        setAiQuotaModalOpen(true);
    };

    const submitAiQuotaForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (aiQuotaForm.processing || !aiQuotaTarget) return;
        aiQuotaForm.transform((data) => ({
            ...data,
            daily_limit: data.daily_limit === '' ? null : Number(data.daily_limit),
            monthly_limit: data.monthly_limit === '' ? null : Number(data.monthly_limit),
        }));
        aiQuotaForm.put(`/admin/ai-dashboard/quotas/${aiQuotaTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setAiQuotaModalOpen(false);
                aiQuotaForm.reset();
            },
        });
    };

    return {
        aiQuotaForm,
        aiQuotaModalOpen,
        closeAiQuotaModal: () => setAiQuotaModalOpen(false),
        aiQuotaTarget,
        openEditAiQuota,
        submitAiQuotaForm,
    };
}
