import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { Artisan } from '@/lib/api';

import { ArtisanCard } from './ArtisanCard';

const base: Artisan = {
    id: 7,
    name: 'Koffi Yao',
    trade: 'Plombier sanitaire',
    score_prosartisan: 720,
    photo_url: null,
    city: 'Cocody',
    availability: null,
};

describe('ArtisanCard', () => {
    it('affiche la disponibilité validée, les horaires et la nuit', () => {
        render(
            <ArtisanCard
                artisan={{
                    ...base,
                    availability: {
                        status: 'occupe',
                        label: "Occupé jusqu'au 15/10/2026",
                        until_date: '2026-10-15',
                        schedule: [{ day: 1, start: '08:00', end: '17:00' }],
                        schedule_summary: 'Lundi au vendredi 08:00–17:00',
                        night_work: true,
                    },
                }}
            />,
        );

        expect(screen.getByTestId('availability-badge')).toHaveTextContent("Occupé jusqu'au 15/10/2026");
        expect(screen.getByText('Lundi au vendredi 08:00–17:00')).toBeInTheDocument();
        expect(screen.getByText('Intervient la nuit')).toBeInTheDocument();
        expect(screen.getByText('Plombier sanitaire')).toBeInTheDocument();
        expect(screen.getByText('Cocody')).toBeInTheDocument();
    });

    it("n'invente ni disponibilité, ni photo, ni commune", () => {
        render(<ArtisanCard artisan={{ ...base, city: null, trade: null }} />);

        expect(screen.queryByTestId('availability-badge')).not.toBeInTheDocument();
        expect(screen.queryByRole('img')).not.toBeInTheDocument();
        expect(screen.getByText('KY')).toBeInTheDocument();
        expect(screen.getByText('Métier non renseigné')).toBeInTheDocument();
        expect(screen.queryByText('Abidjan')).not.toBeInTheDocument();
    });
});
