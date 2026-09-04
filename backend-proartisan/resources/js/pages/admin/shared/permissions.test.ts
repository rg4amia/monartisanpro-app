import { describe, expect, it } from 'vitest';

import { can, canOpenTab } from './permissions';

describe('can()', () => {
    it('accorde tout avec le joker', () => {
        expect(can(['*'], 'admin.kyc.review')).toBe(true);
        expect(can(['*'], 'nimporte.quoi')).toBe(true);
    });

    it('accorde uniquement les capacités explicitement présentes', () => {
        expect(can(['admin.kyc.view'], 'admin.kyc.view')).toBe(true);
        expect(can(['admin.kyc.view'], 'admin.kyc.review')).toBe(false);
    });

    it('refuse quand la liste est absente ou vide', () => {
        expect(can(undefined, 'admin.kyc.view')).toBe(false);
        expect(can([], 'admin.kyc.view')).toBe(false);
    });
});

describe('canOpenTab()', () => {
    it('laisse passer les onglets sans capacité requise', () => {
        expect(canOpenTab([], 'dashboard')).toBe(true);
        expect(canOpenTab([], 'notifications')).toBe(true);
    });

    it('gate les onglets sensibles sur leur capacité', () => {
        expect(canOpenTab(['admin.observability.view'], 'observability')).toBe(true);
        expect(canOpenTab(['admin.kyc.view'], 'observability')).toBe(false);
        expect(canOpenTab(['*'], 'roles_permissions')).toBe(true);
    });
});
