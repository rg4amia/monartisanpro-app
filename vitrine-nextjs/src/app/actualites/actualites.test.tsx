import { render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import ActualitesPage from './page';
import { api, Article } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    getArticles: vi.fn(),
  },
}));

describe('ActualitesPage - Sécurisation XSS (Lot C)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('assainit le contenu HTML des articles et élimine les scripts et handlers inline', async () => {
    const maliciousArticle: Article = {
      id: 1,
      slug: 'chantier-securise',
      titre: 'Conseils pour vos travaux',
      categorie: 'actualite',
      image_url: 'https://example.com/image.jpg',
      contenu: '<p>Contenu légitime</p><script>alert("XSS_ATTACK")</script><img src="x" onerror="evil()" />',
      publie_at: '2026-10-06T12:00:00Z',
    };

    vi.mocked(api.getArticles).mockResolvedValue([maliciousArticle]);

    const { container } = render(<ActualitesPage />);

    // Attendre le chargement
    await waitFor(() => {
      expect(screen.getByText('Conseils pour vos travaux')).toBeInTheDocument();
    });

    expect(screen.getByText('Contenu légitime')).toBeInTheDocument();
    
    // Vérifier l'absence de balise script ou de code malveillant
    expect(container.querySelector('script')).toBeNull();
    expect(container.innerHTML).not.toContain('alert("XSS_ATTACK")');
    expect(container.innerHTML).not.toContain('onerror');
  });
});
