import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPut = vi.fn();
const routerPost = vi.fn();
const routerDelete = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        put: (...args: unknown[]) => routerPut(...args),
        post: (...args: unknown[]) => routerPost(...args),
        delete: (...args: unknown[]) => routerDelete(...args),
    },
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
}));

import type { AppStoreLinkItem, AppStoreLinkOptions } from '../shared';
import { AppStoreLinksPanel } from './AppStoreLinksPanel';

const options: AppStoreLinkOptions = {
    platforms: { android: 'Google Play', ios: 'App Store' },
    statuses: { brouillon: 'Brouillon', publie: 'Publié', desactive: 'Désactivé' },
};

const PLAY = 'https://play.google.com/store/apps/details?id=com.prosartisan.app';

function link(overrides: Partial<AppStoreLinkItem> = {}): AppStoreLinkItem {
    return {
        id: 1,
        platform: 'android',
        platform_label: 'Google Play',
        url: PLAY,
        status: 'brouillon',
        status_label: 'Brouillon',
        created_by: 'Admin',
        created_at: '2026-09-29T10:00:00+00:00',
        published_by: null,
        published_at: null,
        disabled_by: null,
        disabled_at: null,
        ...overrides,
    };
}

describe('AppStoreLinksPanel', () => {
    beforeEach(() => {
        routerPut.mockReset();
        routerPost.mockReset();
        routerDelete.mockReset();
    });

    it('annonce une liste vide et l\'absence de lien publié', () => {
        render(<AppStoreLinksPanel links={[]} options={options} />);

        expect(screen.getByText('Aucun lien')).toBeTruthy();
        expect(screen.getAllByText('Aucun lien publié')).toHaveLength(2);
    });

    it('crée un brouillon pour le magasin choisi', () => {
        render(<AppStoreLinksPanel links={[]} options={options} />);

        fireEvent.change(screen.getByLabelText("Magasin d'applications"), { target: { value: 'ios' } });
        fireEvent.change(screen.getByLabelText('Adresse du lien'), { target: { value: 'https://apps.apple.com/ci/app/prosartisan/id1' } });
        fireEvent.click(screen.getByRole('button', { name: 'Créer le brouillon' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/applications-mobiles',
            { platform: 'ios', url: 'https://apps.apple.com/ci/app/prosartisan/id1' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('affiche le refus du serveur sous le formulaire', () => {
        routerPost.mockImplementation((_url, _data, opts: { onError: (e: Record<string, string>) => void }) => opts.onError({ url: 'Adresse Google Play invalide.' }));
        render(<AppStoreLinksPanel links={[]} options={options} />);

        fireEvent.change(screen.getByLabelText('Adresse du lien'), { target: { value: 'https://exemple.com' } });
        fireEvent.click(screen.getByRole('button', { name: 'Créer le brouillon' }));

        expect(screen.getByRole('alert').textContent).toBe('Adresse Google Play invalide.');
    });

    it('valide un brouillon après confirmation en annonçant le lien remplacé', async () => {
        const current = link({ id: 1, status: 'publie', status_label: 'Publié', published_at: '2026-09-29T11:00:00+00:00' });
        const draft = link({ id: 2, url: 'https://play.google.com/store/apps/details?id=ci.prosartisan.app' });
        render(<AppStoreLinksPanel links={[current, draft]} options={options} />);

        expect(screen.getByText('Affiché sur le site')).toBeTruthy();
        // Un lien publié ne se modifie ni ne se supprime.
        expect(screen.queryByLabelText('Modifier le lien Google Play 1')).toBeNull();
        expect(screen.queryByLabelText('Supprimer le lien Google Play 1')).toBeNull();

        fireEvent.click(screen.getByLabelText('Valider le lien Google Play 2'));
        const dialog = await screen.findByRole('dialog');
        expect(dialog.textContent).toContain(PLAY);
        fireEvent.click(within(dialog).getByRole('button', { name: 'Valider et publier' }));

        await vi.waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/applications-mobiles/2/valider', {}, expect.anything()));
    });

    it('désactive un lien publié après confirmation', async () => {
        render(<AppStoreLinksPanel links={[link({ status: 'publie', status_label: 'Publié' })]} options={options} />);

        fireEvent.click(screen.getByLabelText('Désactiver le lien Google Play 1'));
        fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Désactiver' }));

        await vi.waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/applications-mobiles/1/desactiver', {}, expect.anything()));
    });

    it('modifie puis supprime un lien désactivé', async () => {
        render(<AppStoreLinksPanel links={[link({ status: 'desactive', status_label: 'Désactivé' })]} options={options} />);

        expect(screen.queryByLabelText('Désactiver le lien Google Play 1')).toBeNull();

        fireEvent.click(screen.getByLabelText('Modifier le lien Google Play 1'));
        fireEvent.change(screen.getByLabelText('Nouvelle adresse du lien Google Play'), { target: { value: 'https://play.google.com/store/apps/details?id=a.b' } });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));
        expect(routerPut).toHaveBeenCalledWith('/admin/applications-mobiles/1', { url: 'https://play.google.com/store/apps/details?id=a.b' }, expect.anything());

        fireEvent.click(screen.getByLabelText('Supprimer le lien Google Play 1'));
        fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Supprimer' }));
        await vi.waitFor(() => expect(routerDelete).toHaveBeenCalledWith('/admin/applications-mobiles/1', expect.anything()));
    });
});
