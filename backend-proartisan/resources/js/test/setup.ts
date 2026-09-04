import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Node 22+ expose un `localStorage` expérimental qui masque celui de jsdom et
// n'implémente pas toute l'API. On installe un mock complet et déterministe.
class LocalStorageMock implements Storage {
    private store = new Map<string, string>();

    get length(): number {
        return this.store.size;
    }

    clear(): void {
        this.store.clear();
    }

    getItem(key: string): string | null {
        return this.store.has(key) ? (this.store.get(key) as string) : null;
    }

    setItem(key: string, value: string): void {
        this.store.set(key, String(value));
    }

    removeItem(key: string): void {
        this.store.delete(key);
    }

    key(index: number): string | null {
        return Array.from(this.store.keys())[index] ?? null;
    }
}

Object.defineProperty(window, 'localStorage', {
    value: new LocalStorageMock(),
    configurable: true,
    writable: true,
});

afterEach(() => {
    cleanup();
    window.localStorage.clear();
});
