import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerGet = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        get: (...args: unknown[]) => routerGet(...args),
    },
}));

import { useServerTable } from './useServerTable';

function setSearch(search: string) {
    window.history.replaceState({}, '', `/admin/users${search}`);
}

describe('useServerTable', () => {
    beforeEach(() => {
        routerGet.mockClear();
        window.localStorage.clear();
        setSearch('');
    });

    afterEach(() => setSearch(''));

    const opts = {
        path: '/admin/users',
        only: ['usersPage'],
        initial: { search_users: '', role_users: '' },
        storageKey: 'users',
    };

    it('amorce les filtres depuis l’URL en priorité', () => {
        setSearch('?search_users=jean&role_users=artisan');
        const { result } = renderHook(() => useServerTable(opts));
        expect(result.current.filters).toEqual({ search_users: 'jean', role_users: 'artisan' });
    });

    it('persiste les filtres appliqués dans localStorage', () => {
        const { result } = renderHook(() => useServerTable(opts));

        act(() => result.current.set('search_users', 'martine'));
        act(() => result.current.apply());

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { search_users: 'martine', role_users: '' },
            expect.objectContaining({ only: ['usersPage'], preserveState: true }),
        );
        expect(JSON.parse(window.localStorage.getItem('admin_table_filters:users') ?? '{}')).toMatchObject({
            search_users: 'martine',
        });
    });

    it('restaure les filtres mémorisés au montage quand l’URL est vierge', () => {
        window.localStorage.setItem('admin_table_filters:users', JSON.stringify({ search_users: 'stored', role_users: '' }));

        const { result } = renderHook(() => useServerTable(opts));

        expect(result.current.filters.search_users).toBe('stored');
        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { search_users: 'stored', role_users: '' },
            expect.objectContaining({ replace: true }),
        );
    });

    it('reset vide les filtres et purge le stockage', () => {
        window.localStorage.setItem('admin_table_filters:users', JSON.stringify({ search_users: 'x', role_users: '' }));
        const { result } = renderHook(() => useServerTable(opts));

        act(() => result.current.reset());

        expect(result.current.filters).toEqual({ search_users: '', role_users: '' });
        expect(window.localStorage.getItem('admin_table_filters:users')).toBeNull();
    });
});
