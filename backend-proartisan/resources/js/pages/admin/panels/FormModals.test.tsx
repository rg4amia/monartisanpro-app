import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import {
    AiQuotaFormModal,
    CommunicationFormModal,
    PromoCodeFormModal,
    StatusFormModal,
    UserFormModal,
} from './FormModals';
import type { AdminUser, PromoCodeItem } from '../shared';

/**
 * Ces modales reçoivent l'instance `useForm` d'Inertia du parent plutôt que
 * de l'instancier elles-mêmes : ce faux `form` réactif suffit à les tester
 * isolément, sans mocker `@inertiajs/react`.
 */
function useFakeForm<T extends Record<string, unknown>>(initial: T) {
    const [data, setDataState] = useState(initial);
    return {
        data,
        setData: (key: string, value: unknown) =>
            setDataState((current) => ({ ...current, [key]: value })),
        errors: {} as Record<string, string>,
        processing: false,
    };
}

function CommunicationHarness({
    editing = null,
    onSubmit,
    onClose,
}: {
    editing?: unknown;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    const form = useFakeForm({
        type: 'annonce',
        titre: '',
        contenu: '',
        cibles: [] as string[],
        media_file: null as File | null,
        media_external_url: '',
        media_duration: '',
    });
    return (
        <CommunicationFormModal
            form={form}
            editing={editing}
            adminName="Admin ProsArtisan"
            onSubmit={onSubmit}
            onClose={onClose}
        />
    );
}

describe('CommunicationFormModal', () => {
    it('affiche le titre adapté à la création vs modification', () => {
        const { rerender } = render(
            <CommunicationHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />,
        );
        expect(screen.getByText('Créer une publication')).toBeInTheDocument();

        rerender(
            <CommunicationHarness
                editing={{ id: 1 }}
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
            />,
        );
        expect(screen.getByText('Modifier la publication')).toBeInTheDocument();
    });

    it('affiche le champ audio uniquement pour le type "audio"', () => {
        render(<CommunicationHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);

        expect(screen.queryByText(/MP3, M4A, AAC, OGG ou WAV/)).not.toBeInTheDocument();

        fireEvent.change(screen.getByDisplayValue('Communication interne'), {
            target: { value: 'audio' },
        });

        expect(screen.getByText(/MP3, M4A, AAC, OGG ou WAV/)).toBeInTheDocument();
    });

    it('sélectionne/désélectionne tous les espaces cibles via "TOUS"', () => {
        render(<CommunicationHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);

        fireEvent.click(screen.getByText('TOUS'));

        expect(screen.getByRole('checkbox', { name: 'Clients' })).toBeChecked();
        expect(screen.getByRole('checkbox', { name: 'Artisans' })).toBeChecked();
    });

    it('déclenche onSubmit et onClose', () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        const onClose = vi.fn();
        render(<CommunicationHarness onSubmit={onSubmit} onClose={onClose} />);

        fireEvent.change(screen.getAllByRole('textbox')[0], {
            target: { value: 'Titre de test' },
        });
        // `fireEvent.submit` sur le formulaire contourne la validation HTML5
        // native des champs `required` restés vides (ex: contenu), qui
        // bloquerait sinon l'événement `submit` avant d'atteindre `onSubmit`.
        fireEvent.submit(screen.getByRole('button', { name: 'Enregistrer Brouillon' }).closest('form')!);
        expect(onSubmit).toHaveBeenCalledTimes(1);

        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        expect(onClose).toHaveBeenCalledTimes(1);
    });
});

function PromoCodeHarness({
    editing = null,
    onSubmit,
    onClose,
}: {
    editing?: PromoCodeItem | null;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    const form = useFakeForm({
        code: editing?.code ?? '',
        discount_type: editing?.discount_type ?? 'percent',
        discount_value: editing?.discount_value ?? 0,
        min_order_amount: editing?.min_order_amount ?? 0,
        max_discount_amount: editing?.max_discount_amount ?? 0,
        usage_limit: editing?.usage_limit ?? 0,
        description: editing?.description ?? '',
        expires_at: '',
        is_active: editing?.is_active ?? true,
    });
    return <PromoCodeFormModal form={form} editing={editing} onSubmit={onSubmit} onClose={onClose} />;
}

describe('PromoCodeFormModal', () => {
    it('normalise le code promo en majuscules à la saisie', () => {
        render(<PromoCodeHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);

        fireEvent.change(screen.getByPlaceholderText('ex: PROS225'), { target: { value: 'promo10' } });

        expect(screen.getByDisplayValue('PROMO10')).toBeInTheDocument();
    });

    it('affiche le libellé "Mettre à jour" en mode édition', () => {
        render(
            <PromoCodeHarness
                editing={{ id: 1, code: 'PROMO10' } as PromoCodeItem}
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByText('Modifier le code PROMO10')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mettre à jour' })).toBeInTheDocument();
    });

    it('déclenche onSubmit', () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        render(<PromoCodeHarness onSubmit={onSubmit} onClose={vi.fn()} />);

        fireEvent.submit(screen.getByRole('button', { name: 'Créer le Code Promo' }).closest('form')!);

        expect(onSubmit).toHaveBeenCalledTimes(1);
    });
});

function UserHarness({
    editing = null,
    onSubmit,
    onClose,
    errors = {},
}: {
    editing?: AdminUser | null;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
    errors?: Record<string, string>;
}) {
    const form = useFakeForm({
        name: editing?.name ?? '',
        phone: editing?.phone ?? '',
        email: '',
        role: editing?.role ?? 'client',
        password: '',
        kyc_status: 'en_attente',
        account_status: 'actif',
        score_frozen: false,
        device_fingerprint: '',
        photo: null as File | null,
        documents: { cni: null as File | null, selfie: null as File | null },
    });
    (form as { errors: Record<string, string> }).errors = errors;
    return <UserFormModal form={form} editing={editing} onSubmit={onSubmit} onClose={onClose} />;
}

describe('UserFormModal', () => {
    it('affiche le titre adapté à la création vs modification', () => {
        const { rerender } = render(
            <UserHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />,
        );
        expect(screen.getByText('Créer un utilisateur')).toBeInTheDocument();

        rerender(
            <UserHarness
                editing={{ id: 1, name: 'Awa Traoré' } as AdminUser}
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
            />,
        );
        expect(screen.getByText('Modifier l’utilisateur')).toBeInTheDocument();
    });

    it('n\'exige pas de mot de passe en mode édition', () => {
        render(
            <UserHarness
                editing={{ id: 1, name: 'Awa Traoré' } as AdminUser}
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByPlaceholderText('Laisser vide pour ne pas changer')).not.toBeRequired();
    });

    it('affiche les erreurs de validation du serveur', () => {
        render(
            <UserHarness
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
                errors={{ phone: 'Ce numéro est déjà utilisé.' }}
            />,
        );

        expect(screen.getByText('Ce numéro est déjà utilisé.')).toBeInTheDocument();
    });

    it('déclenche onSubmit et onClose', () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        const onClose = vi.fn();
        render(<UserHarness onSubmit={onSubmit} onClose={onClose} />);

        fireEvent.submit(screen.getByRole('button', { name: 'Enregistrer' }).closest('form')!);
        expect(onSubmit).toHaveBeenCalledTimes(1);

        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it("n'affiche pas la section photo/pièces d'identité en création", () => {
        render(<UserHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);
        expect(screen.queryByText('Photo et pièces d’identité')).not.toBeInTheDocument();
    });

    it('affiche la pièce KYC existante et permet de la remplacer en édition', () => {
        render(
            <UserHarness
                editing={{
                    id: 1,
                    name: 'Awa Traoré',
                    photo_url: null,
                    kyc_documents: [{ type: 'cni', statut: 'approuve', file_url: 'https://example.test/cni.jpg' }],
                } as AdminUser}
                onSubmit={vi.fn((e) => e.preventDefault())}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByText('Photo et pièces d’identité')).toBeInTheDocument();
        expect(screen.getByText('Consulter la pièce actuelle')).toHaveAttribute('href', 'https://example.test/cni.jpg');

        const file = new File(['contenu'], 'nouvelle-cni.jpg', { type: 'image/jpeg' });
        // Le 1er input file est la photo de profil, le 2e la pièce CNI.
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fireEvent.change(fileInputs[1], { target: { files: [file] } });

        expect(screen.getByText(/Remplacera la pièce actuelle/)).toBeInTheDocument();
    });
});

function StatusHarness({
    onSubmit,
    onClose,
}: {
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    const form = useFakeForm({ account_status_reason: '' });
    return (
        <StatusFormModal
            form={form}
            targetUser={{ id: 1, name: 'Awa Traoré' } as AdminUser}
            onSubmit={onSubmit}
            onClose={onClose}
        />
    );
}

describe('StatusFormModal', () => {
    it('affiche le nom de l\'utilisateur ciblé', () => {
        render(<StatusHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);
        expect(screen.getByText('Suspendre le compte de Awa Traoré')).toBeInTheDocument();
    });

    it('déclenche onSubmit avec le motif saisi', () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        render(<StatusHarness onSubmit={onSubmit} onClose={vi.fn()} />);

        fireEvent.change(
            screen.getByPlaceholderText('Ex: Documents non conformes ou comportement abusif signalé...'),
            { target: { value: 'Fraude confirmée' } },
        );
        fireEvent.click(screen.getByRole('button', { name: 'Suspendre le compte' }));

        expect(onSubmit).toHaveBeenCalledTimes(1);
    });
});

function AiQuotaHarness({
    onSubmit,
    onClose,
}: {
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    const form = useFakeForm({ daily_limit: '', monthly_limit: '', blocked: false, note: '' });
    return (
        <AiQuotaFormModal
            form={form}
            targetName="Awa Traoré"
            globalDailyLimit={20}
            globalMonthlyLimit={300}
            onSubmit={onSubmit}
            onClose={onClose}
        />
    );
}

describe('AiQuotaFormModal', () => {
    it('affiche les limites globales par défaut', () => {
        render(<AiQuotaHarness onSubmit={vi.fn((e) => e.preventDefault())} onClose={vi.fn()} />);
        expect(screen.getByText(/20\/j/)).toBeInTheDocument();
        expect(screen.getByText(/300\/mois/)).toBeInTheDocument();
    });

    it('coche le blocage complet et déclenche onSubmit', () => {
        const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
        render(<AiQuotaHarness onSubmit={onSubmit} onClose={vi.fn()} />);

        fireEvent.click(screen.getByRole('checkbox'));
        expect(screen.getByRole('checkbox')).toBeChecked();

        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));
        expect(onSubmit).toHaveBeenCalledTimes(1);
    });
});
