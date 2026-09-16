import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const usePathnameMock = vi.fn(() => '/');
vi.mock('next/navigation', () => ({
    usePathname: () => usePathnameMock(),
}));

import Navbar from './Navbar';

describe('Navbar', () => {
    beforeEach(() => {
        usePathnameMock.mockReturnValue('/');
    });

    it('affiche tous les liens de navigation principaux', () => {
        render(<Navbar />);
        expect(screen.getByRole('link', { name: 'Services' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Artisans' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/contact');
    });

    it('marque le lien "Services" comme actif sur la page correspondante', () => {
        usePathnameMock.mockReturnValue('/services');
        render(<Navbar />);

        const links = screen.getAllByRole('link', { name: 'Services' });
        expect(links[0].className).toContain('text-[#8a5d16]');
    });

    it('n\'active "Accueil" que sur la racine exacte', () => {
        usePathnameMock.mockReturnValue('/services');
        render(<Navbar />);

        const links = screen.getAllByRole('link', { name: 'Accueil' });
        expect(links[0].className).not.toContain('text-[#8a5d16]');
    });

    // `AnimatePresence` (framer-motion) garde le tiroir mobile monté le temps
    // de son animation de sortie, jamais résolue sous jsdom : on observe donc
    // l'icône bascule (Menu ↔ X), non affectée par ce comportement, plutôt
    // que la disparition du contenu du tiroir.
    it('bascule l\'icône du menu mobile entre "Menu" et "Fermer"', () => {
        const { container } = render(<Navbar />);

        expect(container.querySelector('svg.lucide-menu')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Toggle menu'));
        expect(container.querySelector('svg.lucide-x')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Toggle menu'));
        expect(container.querySelector('svg.lucide-menu')).toBeInTheDocument();
    });

    it('referme le menu mobile au clic sur un lien du tiroir', () => {
        const { container } = render(<Navbar />);
        fireEvent.click(screen.getByLabelText('Toggle menu'));
        expect(container.querySelector('svg.lucide-x')).toBeInTheDocument();

        // Lien propre au tiroir mobile (libellé distinct du CTA desktop).
        fireEvent.click(screen.getByText('Espace Fournisseur (Quincaillerie)'));

        expect(container.querySelector('svg.lucide-menu')).toBeInTheDocument();
    });

    it('applique le style "scrolled" au-delà de 20px de défilement', () => {
        const { container } = render(<Navbar />);
        const header = container.querySelector('header')!;
        expect(header.className).toContain('bg-transparent');

        act(() => {
            Object.defineProperty(window, 'scrollY', { value: 50, configurable: true });
            window.dispatchEvent(new Event('scroll'));
        });

        expect(header.className).toContain('bg-white/80');
    });
});
