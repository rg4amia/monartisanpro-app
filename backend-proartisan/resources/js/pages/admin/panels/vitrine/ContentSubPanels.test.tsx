import { fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactElement } from 'react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerDelete = vi.fn();
const formPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { delete: (...args: unknown[]) => routerDelete(...args) },
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) => setDataState((current) => ({ ...current, [key]: value })),
            post: (url: string) => formPost(url, data),
            put: (url: string) => formPost(url, data),
            reset: () => setDataState(initial),
            clearErrors: () => undefined,
            transform: () => undefined,
            processing: false,
            errors: {},
        };
    },
}));

import { ArticlesSubPanel } from './ArticlesSubPanel';
import { ArtisanDuMoisSubPanel } from './ArtisanDuMoisSubPanel';
import { FormationsSubPanel } from './FormationsSubPanel';
import { PopupsSubPanel } from './PopupsSubPanel';
import { RecrutementsSubPanel } from './RecrutementsSubPanel';
import { SlidesSubPanel } from './SlidesSubPanel';
import { VideosSubPanel } from './VideosSubPanel';

const item = {
    id: 7,
    titre: 'Élément de test vitrine',
    categorie: 'Actualité',
    metier: 'Maçon',
    type_contrat: 'cdi',
    lieu: 'Abidjan - Cocody',
    prix: 25000,
    is_active: true,
    is_published: true,
    mois: '2026-09-01',
    texte_editorial: 'Parcours exemplaire.',
    user: { name: 'Kouamé Artisan', trade: 'Plombier', score_prosartisan: 812 },
};

// Chaque sous-panneau de contenu partage le même geste : suppression après
// confirmation dans la modale accessible, puis `router.delete` vers sa ressource.
const cases: Array<{ name: string; element: ReactElement; label: string; deleteButton: string; url: string }> = [
    { name: 'Slides', element: <SlidesSubPanel slides={[item]} />, label: item.titre, deleteButton: 'Supprimer', url: '/admin/vitrine/slides/7' },
    { name: 'Articles', element: <ArticlesSubPanel articles={[item]} />, label: item.titre, deleteButton: 'Supprimer', url: '/admin/vitrine/articles/7' },
    { name: 'Vidéos', element: <VideosSubPanel videos={[item]} />, label: item.titre, deleteButton: 'Supprimer', url: '/admin/vitrine/videos/7' },
    {
        name: 'Formations',
        element: <FormationsSubPanel formations={[item]} money={(v) => `${v} FCFA`} />,
        label: item.titre,
        deleteButton: 'Supprimer',
        url: '/admin/vitrine/formations/7',
    },
    { name: 'Recrutements', element: <RecrutementsSubPanel recrutements={[item]} />, label: item.titre, deleteButton: 'Supprimer', url: '/admin/vitrine/recrutements/7' },
    { name: 'Pop-ups', element: <PopupsSubPanel popups={[item]} />, label: item.titre, deleteButton: 'Supprimer', url: '/admin/vitrine/popups/7' },
    {
        name: 'Artisan du mois',
        element: <ArtisanDuMoisSubPanel admList={[item]} artisans={[]} />,
        label: 'Kouamé Artisan',
        deleteButton: 'Retirer la mise en avant',
        url: '/admin/vitrine/artisan-du-mois/7',
    },
];

describe('Sous-panneaux de contenu de la vitrine', () => {
    beforeEach(() => {
        routerDelete.mockClear();
        formPost.mockClear();
    });

    it.each(cases)('$name : affiche l’élément et le supprime après confirmation', async ({ element, label, deleteButton, url }) => {
        render(element);

        expect(screen.getAllByText(label, { exact: false }).length).toBeGreaterThan(0);

        const buttons = screen.getAllByRole('button', { name: deleteButton });
        fireEvent.click(buttons[buttons.length - 1]);

        const dialog = await screen.findByRole('dialog');
        expect(routerDelete).not.toHaveBeenCalled();
        fireEvent.click(within(dialog).getByRole('button', { name: 'Supprimer' }));

        await vi.waitFor(() => expect(routerDelete).toHaveBeenCalledWith(url, expect.objectContaining({ preserveScroll: true })));
    });

    it.each(cases)('$name : annuler la confirmation ne supprime rien', async ({ element, deleteButton }) => {
        render(element);

        const buttons = screen.getAllByRole('button', { name: deleteButton });
        fireEvent.click(buttons[buttons.length - 1]);

        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        await vi.waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(routerDelete).not.toHaveBeenCalled();
    });

    it('affiche une mention explicite quand aucun élément n’existe', () => {
        const { container } = render(<SlidesSubPanel slides={[]} />);
        expect(container.textContent).not.toContain(item.titre);
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();
    });
});

