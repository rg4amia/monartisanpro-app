import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useConfirm } from './ConfirmDialog';
import type { ConfirmOptions } from './ConfirmDialog';

function Harness({ options, onResult }: { options: ConfirmOptions; onResult: (v: boolean | string) => void }) {
    const { confirm, dialog } = useConfirm();
    return (
        <>
            <button type="button" onClick={() => confirm(options).then(onResult)}>
                déclencher
            </button>
            {dialog}
        </>
    );
}

describe('useConfirm', () => {
    it('résout true à la confirmation et false à l’annulation', async () => {
        const onResult = vi.fn();
        render(<Harness options={{ title: 'Supprimer ?' }} onResult={onResult} />);

        fireEvent.click(screen.getByRole('button', { name: 'déclencher' }));
        expect(screen.getByRole('dialog')).toHaveTextContent('Supprimer ?');

        fireEvent.click(screen.getByRole('button', { name: 'Confirmer' }));
        await waitFor(() => expect(onResult).toHaveBeenCalledWith(true));

        fireEvent.click(screen.getByRole('button', { name: 'déclencher' }));
        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        await waitFor(() => expect(onResult).toHaveBeenLastCalledWith(false));
    });

    it('bloque la confirmation tant que le mot requis n’est pas saisi', async () => {
        const onResult = vi.fn();
        render(<Harness options={{ title: 'Anonymiser', requireText: 'ANONYMISER' }} onResult={onResult} />);

        fireEvent.click(screen.getByRole('button', { name: 'déclencher' }));
        expect(screen.getByRole('button', { name: 'Confirmer' })).toBeDisabled();

        fireEvent.change(screen.getByLabelText('Tapez ANONYMISER pour confirmer'), { target: { value: 'ANONYMISER' } });
        expect(screen.getByRole('button', { name: 'Confirmer' })).toBeEnabled();

        fireEvent.click(screen.getByRole('button', { name: 'Confirmer' }));
        await waitFor(() => expect(onResult).toHaveBeenCalledWith(true));
    });

    it('ferme sur Échap en résolvant false', async () => {
        const onResult = vi.fn();
        render(<Harness options={{ title: 'Confirmer ?' }} onResult={onResult} />);

        fireEvent.click(screen.getByRole('button', { name: 'déclencher' }));
        fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(onResult).toHaveBeenCalledWith(false));
    });

    it('renvoie la valeur saisie quand promptLabel est fourni', async () => {
        const onResult = vi.fn();
        render(<Harness options={{ title: 'Rejeter', promptLabel: 'Motif', promptMinLength: 4 }} onResult={onResult} />);

        fireEvent.click(screen.getByRole('button', { name: 'déclencher' }));
        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'ok' } });
        expect(screen.getByRole('button', { name: 'Confirmer' })).toBeDisabled();

        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'motif valable' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirmer' }));
        await waitFor(() => expect(onResult).toHaveBeenCalledWith('motif valable'));
    });
});
