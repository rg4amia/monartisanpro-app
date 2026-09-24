import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Pagination serveur des listes du backoffice (Règle d'or 19) : chaque lien
 * recharge uniquement les props `only` de la page courante.
 */
export function renderPagination(links: PaginationLink[] | undefined | null, only: string[] = ['allNotifications']) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="flex justify-center gap-1.5 mt-5">
            {links.map((link, idx) => (
                <Link
                    key={idx}
                    href={link.url || '#'}
                    className={cn(
                        'px-3 py-1.5 rounded-xl text-xs font-semibold border transition',
                        link.active
                            ? 'bg-[#ebb95e] border-[#ebb95e] text-[#241b16]'
                            : 'border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-[var(--admin-panel)]',
                        !link.url && 'opacity-50 cursor-not-allowed',
                    )}
                    only={only}
                    preserveScroll
                    preserveState
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
