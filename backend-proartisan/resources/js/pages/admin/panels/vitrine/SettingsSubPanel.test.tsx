import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const formPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) => setDataState((current) => ({ ...current, [key]: value })),
            post: (url: string) => formPost(url, data),
            processing: false,
            errors: {},
        };
    },
}));

import { SettingsSubPanel } from './SettingsSubPanel';

describe('SettingsSubPanel', () => {
    beforeEach(() => formPost.mockClear());

    it('préremplit le formulaire avec les réglages enregistrés plutôt que les valeurs par défaut', () => {
        render(<SettingsSubPanel settings={[{ cle: 'contact_email', valeur: 'support@prosartisan.ci' }]} />);

        expect(screen.getByDisplayValue('support@prosartisan.ci')).toBeInTheDocument();
        expect(screen.queryByDisplayValue('info@prosartisan.net')).not.toBeInTheDocument();
    });

    it('envoie les réglages modifiés à la route de la vitrine', () => {
        render(<SettingsSubPanel settings={[]} />);

        fireEvent.change(screen.getByDisplayValue('info@prosartisan.net'), { target: { value: 'contact@prosartisan.ci' } });
        fireEvent.click(screen.getAllByRole('button', { name: /Enregistrer/ })[0]);

        expect(formPost).toHaveBeenCalledWith('/admin/vitrine/settings', expect.objectContaining({ contact_email: 'contact@prosartisan.ci' }));
    });
});
