import { describe, it, expect } from 'vitest';
import { sanitizeHtml } from './sanitize';

describe('sanitizeHtml', () => {
  it('retourne une chaîne vide pour les entrées null, undefined ou vides', () => {
    expect(sanitizeHtml(null)).toBe('');
    expect(sanitizeHtml(undefined)).toBe('');
    expect(sanitizeHtml('')).toBe('');
  });

  it('préserve les balises de mise en forme légitimes', () => {
    const safeHtml = '<p>Bienvenue sur <strong>ProsArtisan</strong>, la plateforme des <em>artisans</em>.</p>';
    const result = sanitizeHtml(safeHtml);
    expect(result).toContain('<p>');
    expect(result).toContain('<strong>ProsArtisan</strong>');
    expect(result).toContain('<em>artisans</em>');
  });

  it('élimine les balises script et leur charge utile malveillante', () => {
    const dirty = '<p>Article intéressant</p><script>alert("XSS")</script>';
    const result = sanitizeHtml(dirty);
    expect(result).not.toContain('<script>');
    expect(result).not.toContain('alert("XSS")');
    expect(result).toContain('<p>Article intéressant</p>');
  });

  it('neutralise les attributs d\'événements inline on* (onerror, onclick, onload)', () => {
    const dirty = '<img src="inconnu.jpg" onerror="alert(\'pwned\')" /><a href="#" onclick="evil()">Cliquez ici</a>';
    const result = sanitizeHtml(dirty);
    expect(result).not.toContain('onerror');
    expect(result).not.toContain('onclick');
    expect(result).not.toContain('alert');
  });

  it('neutralise les URLs avec protocole javascript: malveillant', () => {
    const dirty = '<a href="javascript:alert(\'hack\')">Lien suspect</a>';
    const result = sanitizeHtml(dirty);
    expect(result).not.toContain('javascript:alert');
  });

  it('neutralise les balises iframe injectées arbitrairement', () => {
    const dirty = '<p>Texte normal</p><iframe src="https://evil.com/phishing"></iframe>';
    const result = sanitizeHtml(dirty);
    expect(result).not.toContain('<iframe');
    expect(result).not.toContain('https://evil.com/phishing');
    expect(result).toContain('Texte normal');
  });

  it('nettoie efficacement en environnement SSR quand window est absent', () => {
    const originalWindow = globalThis.window;
    try {
      // @ts-expect-error suppression de window pour simuler SSR
      delete globalThis.window;
      const dirty = '<p>SSR</p><script>alert(1)</script><img src="x" onerror="evil()" />';
      const result = sanitizeHtml(dirty);
      expect(result).not.toContain('<script>');
      expect(result).not.toContain('onerror');
      expect(result).toContain('<p>SSR</p>');
    } finally {
      globalThis.window = originalWindow;
    }
  });
});
