import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

// Les sous-panneaux sont testés individuellement (panels/vitrine) : ici on ne
// vérifie que l'aiguillage des onglets et le badge des nouvelles demandes.
vi.mock('./panels/vitrine/ContactsSubPanel', () => ({ ContactsSubPanel: () => <div>panneau-contacts</div> }));
vi.mock('./panels/vitrine/SlidesSubPanel', () => ({ SlidesSubPanel: () => <div>panneau-slides</div> }));
vi.mock('./panels/vitrine/ArtisanDuMoisSubPanel', () => ({
    ArtisanDuMoisSubPanel: ({ artisans }: { artisans: Array<{ name: string }> }) => (
        <div>panneau-artisan-du-mois : {artisans.map((a) => a.name).join(', ')}</div>
    ),
}));
vi.mock('./panels/vitrine/ArticlesSubPanel', () => ({ ArticlesSubPanel: () => <div>panneau-articles</div> }));
vi.mock('./panels/vitrine/VideosSubPanel', () => ({ VideosSubPanel: () => <div>panneau-videos</div> }));
vi.mock('./panels/vitrine/FormationsSubPanel', () => ({ FormationsSubPanel: () => <div>panneau-formations</div> }));
vi.mock('./panels/vitrine/RecrutementsSubPanel', () => ({ RecrutementsSubPanel: () => <div>panneau-recrutements</div> }));
vi.mock('./panels/vitrine/PopupsSubPanel', () => ({ PopupsSubPanel: () => <div>panneau-popups</div> }));
vi.mock('./panels/vitrine/SettingsSubPanel', () => ({ SettingsSubPanel: () => <div>panneau-parametres</div> }));

import VitrinePanel from './vitrine-panel';

const baseProps = {
    vitrineSlides: [],
    vitrineArtisanDuMois: [],
    vitrineArticles: [],
    vitrineVideos: [],
    vitrineFormations: [],
    vitrineRecrutements: [],
    vitrinePopups: [],
    vitrineSettings: [],
    contactMessages: [],
    users: [],
};

describe('VitrinePanel', () => {
    it('ouvre l’onglet des demandes de contact par défaut', () => {
        render(<VitrinePanel {...baseProps} />);
        expect(screen.getByText('panneau-contacts')).toBeInTheDocument();
    });

    it('bascule vers le sous-panneau choisi', () => {
        render(<VitrinePanel {...baseProps} />);

        fireEvent.click(screen.getByRole('button', { name: 'Paramètres Généraux' }));

        expect(screen.getByText('panneau-parametres')).toBeInTheDocument();
        expect(screen.queryByText('panneau-contacts')).not.toBeInTheDocument();
    });

    it('signale le nombre de nouvelles demandes de contact', () => {
        render(
            <VitrinePanel
                {...baseProps}
                contactMessages={[{ statut: 'nouveau' }, { statut: 'nouveau' }, { statut: 'traite' }]}
            />,
        );

        expect(screen.getByText(/2 nouvelles demandes de contact à traiter/)).toBeInTheDocument();
    });

    it('ne propose que des artisans pour la mise en avant du mois', () => {
        render(
            <VitrinePanel
                {...baseProps}
                users={[
                    { name: 'Kouamé', role: 'artisan' },
                    { name: 'Awa', role: 'client' },
                ]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Artisan du Mois' }));

        expect(screen.getByText('panneau-artisan-du-mois : Kouamé')).toBeInTheDocument();
    });
});
