import DOMPurify from 'dompurify';

/** Balises de mise en forme conservées hors navigateur, toujours sans attribut. */
const SERVER_ALLOWED_TAGS = new Set([
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
]);

/**
 * Nettoie le HTML hors navigateur (rendu serveur), où DOMPurify n'a pas de DOM.
 * Liste fermée : seules des balises de mise en forme passent, et sans aucun
 * attribut. Retirer les seuls motifs dangereux connus se contournait
 * (`<svg/onload=…>`, `href=javascript:…` sans guillemets).
 */
function sanitizeServerHtml(dirty: string): string {
  if (!dirty) return '';

  return dirty
    // Contenu des balises dont le texte n'est pas du texte à afficher.
    .replace(/<(script|style|iframe|object|noscript|template)\b[\s\S]*?<\/\1\s*>/gi, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>?/g, (tag, name: string) => {
      const lower = name.toLowerCase();
      if (!SERVER_ALLOWED_TAGS.has(lower)) return '';
      return tag.startsWith('</') ? `</${lower}>` : `<${lower}>`;
    });
}

/**
 * Assainit une chaîne HTML pour prévenir les injections XSS
 * aussi bien côté client (DOMPurify certifié) que côté serveur (filtre défensif SSR).
 */
export function sanitizeHtml(dirty?: string | null): string {
  if (!dirty || typeof dirty !== 'string') {
    return '';
  }

  if (typeof window !== 'undefined') {
    // Si DOMPurify est une fonction factory (Node / certains environnements), instancier avec window
    const purifier = typeof DOMPurify === 'function' ? (DOMPurify as unknown as (win: Window) => typeof DOMPurify)(window) : DOMPurify;
    if (purifier && typeof purifier.sanitize === 'function') {
      return purifier.sanitize(dirty, {
        USE_PROFILES: { html: true },
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'src', 'alt', 'title', 'id', 'width', 'height', 'style'],
      });
    }
  }

  return sanitizeServerHtml(dirty);
}
