// Onglet « Manuel d'utilisation » du backoffice : consultation et
// téléchargement du manuel (source unique docs/produit/manuel-utilisation.html).

import { EmptyState, Surface } from '../shared';
import type { UserManualSummary } from '../shared';

export const MANUAL_DOCUMENT_URL = '/admin/manuel/document';
export const MANUAL_DOWNLOAD_URL = '/admin/manuel/telecharger';

interface ManualPanelProps {
    manual?: UserManualSummary | null;
}

function formatUpdatedAt(value: string | null): string | null {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function ManualPanel({ manual }: ManualPanelProps) {
    if (!manual?.available || !manual.html) {
        return (
            <Surface className="rounded-[30px] p-5 lg:p-6">
                <EmptyState
                    title="Manuel introuvable"
                    description="Le fichier docs/produit/manuel-utilisation.html est absent du serveur. Il est livré avec le dépôt : vérifiez le dernier déploiement."
                />
            </Surface>
        );
    }

    const updatedAt = formatUpdatedAt(manual.updatedAt);

    return (
        <Surface className="rounded-[30px] p-5 lg:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-semibold text-[var(--admin-text)]">Manuel d'utilisation ProsArtisan</h3>
                    <p className="mt-1 text-sm text-[var(--admin-text-soft)]">
                        Guide par espace : client, artisan, fournisseur, livreur, référent et backoffice.
                        {updatedAt ? ` Mis à jour le ${updatedAt}` : ''}
                        {manual.sizeKb ? ` · ${manual.sizeKb} Ko` : ''}.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a
                        href={MANUAL_DOCUMENT_URL}
                        target="_blank"
                        rel="noopener"
                        className="rounded-full border border-[var(--admin-border)] px-4 py-2 text-sm font-semibold text-[var(--admin-text)] transition hover:bg-[var(--admin-panel-strong)]"
                    >
                        Ouvrir en plein écran
                    </a>
                    <a
                        href={MANUAL_DOWNLOAD_URL}
                        download
                        className="rounded-full bg-[var(--admin-text)] px-4 py-2 text-sm font-semibold text-[var(--admin-panel)] transition hover:opacity-90"
                    >
                        Télécharger (HTML)
                    </a>
                </div>
            </div>

            {/* srcdoc plutôt qu'une URL : le backoffice interdit tout encadrement
                de ses pages (X-Frame-Options: DENY). Aucun script autorisé. */}
            <iframe
                title="Manuel d'utilisation ProsArtisan"
                srcDoc={manual.html}
                sandbox=""
                className="mt-5 h-[75vh] w-full rounded-2xl border border-[var(--admin-border)] bg-white"
            />
        </Surface>
    );
}
