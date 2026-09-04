// Bouton d'export CSV d'une liste du backoffice (Chantier C5 / P1-8).
//
// C'est un vrai lien de téléchargement (`<a download>`, hors routeur Inertia) qui
// reprend les filtres serveur actifs de la liste. Le backend renvoie un flux CSV.

interface ExportButtonProps {
    /** Ressource exportée : users | transactions | missions | evaluations | litiges. */
    resource: string;
    /** Filtres actifs à transmettre (mêmes clés que les query params de la liste). */
    params?: Record<string, string>;
    label?: string;
}

export function ExportButton({ resource, params = {}, label = 'Exporter CSV' }: ExportButtonProps) {
    const query = new URLSearchParams(
        Object.entries(params).filter(([, value]) => value !== '' && value != null),
    ).toString();

    const href = `/admin/exports/${resource}${query ? `?${query}` : ''}`;

    return (
        <a
            href={href}
            className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--admin-border)] bg-white/70 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white"
        >
            <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            {label}
        </a>
    );
}
