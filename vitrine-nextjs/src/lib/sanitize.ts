import DOMPurify from 'dompurify';

/**
 * Nettoie défensivement le HTML en environnement serveur (SSR)
 * Élimine les scripts, handlers d'événements inline, iframes et protocoles suspects.
 */
function sanitizeServerHtml(dirty: string): string {
  if (!dirty) return '';

  let clean = dirty;

  // Supprimer les balises script et leur contenu
  clean = clean.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

  // Supprimer les balises iframe, object, embed
  clean = clean.replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '');
  clean = clean.replace(/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi, '');
  clean = clean.replace(/<embed\b[^>]*>/gi, '');

  // Supprimer les attributs on* (onclick, onerror, onload, etc.)
  clean = clean.replace(/\s+on[a-z]+(\s*=\s*(?:'[^']*'|"[^"]*"|[^\s>]+))?/gi, '');

  // Supprimer les schémas javascript: et data: dangereux
  clean = clean.replace(/(href|src)\s*=\s*(['"])\s*(?:javascript|vbscript|data):.*?\2/gi, '$1="#"');

  return clean;
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
