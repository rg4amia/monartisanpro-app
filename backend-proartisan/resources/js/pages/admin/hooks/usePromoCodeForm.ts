import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type React from 'react';
import type { AskConfirm, PromoCodeItem } from '../shared';

const EMPTY_PROMO = {
    code: '',
    description: '',
    discount_type: 'percent' as 'percent' | 'fixed',
    discount_value: 10,
    min_order_amount: 0,
    max_discount_amount: 0,
    usage_limit: 0,
    starts_at: '',
    expires_at: '',
    is_active: true,
};

/** Création, édition, activation et suppression des codes promo. */
export function usePromoCodeForm(askConfirm: AskConfirm) {
    const [promoModalOpen, setPromoModalOpen] = useState<boolean>(false);
    const [editingPromo, setEditingPromo] = useState<PromoCodeItem | null>(null);
    const promoForm = useForm({ ...EMPTY_PROMO });

    const openCreatePromoModal = (): void => {
        setEditingPromo(null);
        promoForm.reset();
        promoForm.clearErrors();
        promoForm.setData({ ...EMPTY_PROMO });
        setPromoModalOpen(true);
    };

    const openEditPromoModal = (promo: PromoCodeItem): void => {
        setEditingPromo(promo);
        promoForm.clearErrors();
        promoForm.setData({
            code: promo.code,
            description: promo.description || '',
            discount_type: promo.discount_type,
            discount_value: promo.discount_value,
            min_order_amount: promo.min_order_amount || 0,
            max_discount_amount: promo.max_discount_amount || 0,
            usage_limit: promo.usage_limit || 0,
            starts_at: promo.starts_at ? promo.starts_at.slice(0, 10) : '',
            expires_at: promo.expires_at ? promo.expires_at.slice(0, 10) : '',
            is_active: promo.is_active,
        });
        setPromoModalOpen(true);
    };

    const submitPromoForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (promoForm.processing) return;

        promoForm.transform(() => ({
            ...promoForm.data,
            code: promoForm.data.code.trim().toUpperCase(),
            min_order_amount: Number(promoForm.data.min_order_amount) || 0,
            max_discount_amount: Number(promoForm.data.max_discount_amount) || null,
            usage_limit: Number(promoForm.data.usage_limit) || null,
            starts_at: promoForm.data.starts_at || null,
            expires_at: promoForm.data.expires_at || null,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setPromoModalOpen(false);
                promoForm.reset();
            },
        };
        if (editingPromo) {
            promoForm.put(`/admin/promo-codes/${editingPromo.id}`, options);
        } else {
            promoForm.post('/admin/promo-codes', options);
        }
    };

    const deletePromo = async (promo: PromoCodeItem): Promise<void> => {
        const ok = await askConfirm({
            title: 'Supprimer le code promo',
            message: `Supprimer définitivement le code promo "${promo.code}" ?`,
            tone: 'danger',
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/admin/promo-codes/${promo.id}`, { preserveScroll: true });
    };

    const togglePromo = (promo: PromoCodeItem): void => {
        router.post(`/admin/promo-codes/${promo.id}/toggle`, {}, { preserveScroll: true });
    };

    return {
        promoForm,
        promoModalOpen,
        closePromoModal: () => setPromoModalOpen(false),
        editingPromo,
        openCreatePromoModal,
        openEditPromoModal,
        submitPromoForm,
        deletePromo,
        togglePromo,
    };
}
