import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type React from 'react';
import type { AdminUser, AskConfirm } from '../shared';

interface CurrentAdmin {
    phone?: string | null;
    email?: string | null;
}

interface UserManagementOptions {
    currentAdmin?: CurrentAdmin | null;
    askConfirm: AskConfirm;
    setActionLoading: (loading: boolean) => void;
    canDeleteUsers: boolean;
    canImpersonate: boolean;
    canManageRgpd: boolean;
}

export interface UserFormData {
    name: string;
    phone: string;
    email: string;
    role: string;
    password: string;
    kyc_status: string;
    account_status: string;
    score_frozen: boolean;
    device_fingerprint: string;
    photo: File | null;
    documents: { cni: File | null; selfie: File | null };
    fournisseur_sector_id: number | '';
    fournisseur_trade_id: number | '';
}

const EMPTY_USER: UserFormData = {
    name: '',
    phone: '',
    email: '',
    role: 'client',
    password: '',
    kyc_status: 'en_attente',
    account_status: 'actif',
    score_frozen: false,
    device_fingerprint: '',
    photo: null,
    documents: { cni: null, selfie: null },
    fournisseur_sector_id: '',
    fournisseur_trade_id: '',
};

/**
 * Gestion des comptes depuis l'onglet Utilisateurs : création/édition,
 * suspension motivée, suppression, usurpation de session (Règle d'or 24) et
 * anonymisation RGPD (Règle d'or 22). Un administrateur ne peut jamais agir
 * sur son propre compte.
 */
export function useUserManagement({ currentAdmin, askConfirm, setActionLoading, canDeleteUsers, canImpersonate, canManageRgpd }: UserManagementOptions) {
    const [userModalOpen, setUserModalOpen] = useState<boolean>(false);
    const [editingUser, setEditingUser] = useState<AdminUser | null>(null);
    const [statusModalOpen, setStatusModalOpen] = useState<boolean>(false);
    const [statusTargetUser, setStatusTargetUser] = useState<AdminUser | null>(null);
    const [selectedUserForRgpd, setSelectedUserForRgpd] = useState<AdminUser | null>(null);

    const userForm = useForm<UserFormData>({ ...EMPTY_USER });
    const statusForm = useForm({
        account_status: 'actif',
        account_status_reason: '',
    });

    const isSelf = (user: AdminUser): boolean => currentAdmin?.phone === user.phone || currentAdmin?.email === user.email;

    const refuseSelf = async (message: string): Promise<void> => {
        await askConfirm({ title: 'Action impossible', message, confirmLabel: 'Compris', cancelLabel: 'Fermer' });
    };

    const openCreateUserModal = (): void => {
        setEditingUser(null);
        userForm.reset();
        userForm.clearErrors();
        userForm.setData({ ...EMPTY_USER });
        setUserModalOpen(true);
    };

    const openEditUserModal = (user: AdminUser): void => {
        setEditingUser(user);
        userForm.clearErrors();
        userForm.setData({
            name: user.name,
            phone: user.phone,
            email: user.email ?? '',
            role: user.role as string,
            password: '',
            kyc_status: user.kyc_status as string,
            account_status: (user.account_status ?? 'actif') as string,
            score_frozen: Boolean(user.score_frozen),
            device_fingerprint: user.device_fingerprint ?? '',
            photo: null,
            documents: { cni: null, selfie: null },
            fournisseur_sector_id: user.fournisseur_sector_id ?? '',
            fournisseur_trade_id: user.fournisseur_trade_id ?? '',
        });
        setUserModalOpen(true);
    };

    const submitUserForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setUserModalOpen(false);
                userForm.reset();
            },
        };
        if (editingUser) {
            userForm.put(`/admin/users/${editingUser.id}`, options);
        } else {
            userForm.post('/admin/users', options);
        }
    };

    const toggleUserStatus = async (user: AdminUser): Promise<void> => {
        if (isSelf(user)) {
            await refuseSelf('Vous ne pouvez pas modifier votre propre statut.');
            return;
        }

        const isActif = (user.account_status ?? 'actif') === 'actif';
        if (isActif) {
            setStatusTargetUser(user);
            statusForm.reset();
            statusForm.clearErrors();
            statusForm.setData({ account_status: 'suspendu', account_status_reason: '' });
            setStatusModalOpen(true);
            return;
        }

        const ok = await askConfirm({
            title: 'Réactiver le compte',
            message: `Voulez-vous réactiver le compte de ${user.name} ?`,
            confirmLabel: 'Réactiver',
        });
        if (!ok) return;
        router.post(`/admin/users/${user.id}/toggle-status`, { account_status: 'actif', account_status_reason: '' }, { preserveScroll: true });
    };

    const submitStatusForm = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!statusTargetUser) return;

        statusForm.post(`/admin/users/${statusTargetUser.id}/toggle-status`, {
            preserveScroll: true,
            onSuccess: () => {
                setStatusModalOpen(false);
                statusForm.reset();
            },
        });
    };

    const deleteUser = async (user: AdminUser): Promise<void> => {
        if (!canDeleteUsers) return;
        if (isSelf(user)) {
            await refuseSelf('Vous ne pouvez pas supprimer votre propre compte.');
            return;
        }

        const ok = await askConfirm({
            title: `Supprimer ${user.name} ?`,
            message: 'Cette action est irréversible.',
            tone: 'danger',
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
    };

    const impersonate = async (user: AdminUser): Promise<void> => {
        if (!canImpersonate) return;
        const ok = await askConfirm({
            title: `Se connecter en tant que ${user.name} ?`,
            message: 'Votre session basculera sur ce compte. Un bandeau permettra de revenir à votre compte administrateur.',
            confirmLabel: 'Usurper la session',
        });
        if (!ok) return;
        router.post(`/admin/users/${user.id}/impersonate`);
    };

    const anonymize = async (user: AdminUser): Promise<void> => {
        if (!canManageRgpd) return;
        const ok = await askConfirm({
            title: `Anonymiser le compte #${user.id}`,
            message: `Anonymisation RGPD irréversible de ${user.name}. Toutes les données personnelles seront expurgées.`,
            tone: 'danger',
            confirmLabel: 'Anonymiser',
            requireText: 'ANONYMISER',
        });
        if (!ok) return;
        setActionLoading(true);
        router.post(`/admin/users/${user.id}/anonymize`, {}, {
            preserveScroll: true,
            onSuccess: () => setSelectedUserForRgpd(null),
            onFinish: () => setActionLoading(false),
        });
    };

    return {
        userForm,
        statusForm,
        userModalOpen,
        closeUserModal: () => setUserModalOpen(false),
        editingUser,
        statusModalOpen,
        closeStatusModal: () => setStatusModalOpen(false),
        statusTargetUser,
        selectedUserForRgpd,
        setSelectedUserForRgpd,
        openCreateUserModal,
        openEditUserModal,
        submitUserForm,
        toggleUserStatus,
        submitStatusForm,
        deleteUser,
        impersonate,
        anonymize,
    };
}
