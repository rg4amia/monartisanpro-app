import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const put = vi.fn();
const setData = vi.fn();
let formErrors: Record<string, string> = {};

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, string>) => ({
        data: initial,
        setData,
        put,
        processing: false,
        errors: formErrors,
        recentlySuccessful: false,
    }),
}));

import { BankTransferSettingsCard } from './BankTransferSettingsCard';

const empty = { bank_name: '', account_name: '', iban: '' };
const configured = { bank_name: 'Banque Atlantique CI', account_name: 'PROSARTISAN SEQUESTRE', iban: 'CI93 CI00 8011 1301 1342 9120 0589' };

describe('BankTransferSettingsCard', () => {
    beforeEach(() => {
        put.mockClear();
        setData.mockClear();
        formErrors = {};
    });

    it('signale que le virement est refusé tant que les coordonnées manquent', () => {
        render(<BankTransferSettingsCard settings={empty} />);

        expect(screen.getByRole('alert')).toHaveTextContent('le paiement par virement est actuellement refusé');
    });

    it('préremplit les coordonnées enregistrées sans alerte', () => {
        render(<BankTransferSettingsCard settings={configured} />);

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        expect(screen.getByDisplayValue(configured.iban)).toBeInTheDocument();
    });

    it('enregistre via PUT /admin/settings/bank-transfer', () => {
        render(<BankTransferSettingsCard settings={configured} />);

        fireEvent.change(screen.getByLabelText('IBAN'), { target: { value: 'CI93CI0080111301134291200589' } });
        expect(setData).toHaveBeenCalledWith('iban', 'CI93CI0080111301134291200589');

        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les coordonnées' }));
        expect(put).toHaveBeenCalledWith('/admin/settings/bank-transfer', { preserveScroll: true });
    });

    it('affiche l’erreur de validation renvoyée par le serveur', () => {
        formErrors = { iban: "La clé de contrôle de l'IBAN est invalide : vérifiez sa saisie." };
        render(<BankTransferSettingsCard settings={configured} />);

        expect(screen.getByText(/clé de contrôle de l'IBAN est invalide/)).toBeInTheDocument();
    });
});
