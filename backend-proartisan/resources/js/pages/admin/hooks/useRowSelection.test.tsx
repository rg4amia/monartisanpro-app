import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { useRowSelection } from './useRowSelection';

describe('useRowSelection', () => {
    it('bascule une ligne et expose count / ids', () => {
        const { result } = renderHook(() => useRowSelection());

        act(() => result.current.toggle(1));
        act(() => result.current.toggle(2));
        expect(result.current.count).toBe(2);
        expect(result.current.ids.sort()).toEqual([1, 2]);
        expect(result.current.isSelected(1)).toBe(true);

        act(() => result.current.toggle(1));
        expect(result.current.count).toBe(1);
        expect(result.current.isSelected(1)).toBe(false);
    });

    it('toggleAll coche toute la page puis la décoche', () => {
        const { result } = renderHook(() => useRowSelection());
        const pageIds = [10, 11, 12];

        act(() => result.current.toggleAll(pageIds));
        expect(result.current.count).toBe(3);

        act(() => result.current.toggleAll(pageIds));
        expect(result.current.count).toBe(0);
    });

    it('toggleAll conserve les sélections hors page', () => {
        const { result } = renderHook(() => useRowSelection());

        act(() => result.current.toggle(99));
        act(() => result.current.toggleAll([1, 2]));
        expect(result.current.ids.sort((a, b) => a - b)).toEqual([1, 2, 99]);

        act(() => result.current.toggleAll([1, 2]));
        expect(result.current.ids).toEqual([99]);
    });

    it('clear vide la sélection', () => {
        const { result } = renderHook(() => useRowSelection());
        act(() => result.current.toggleAll([1, 2, 3]));
        act(() => result.current.clear());
        expect(result.current.count).toBe(0);
    });
});
