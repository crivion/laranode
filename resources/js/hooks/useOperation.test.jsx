import { renderHook, act } from '@testing-library/react';
import { test, expect, vi, beforeEach } from 'vitest';
import useOperation from '@/hooks/useOperation';

let captured;
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

beforeEach(() => {
    captured = null;
    window.Echo = {
        private: () => ({ listen: (_name, cb) => { captured = cb; }, stopListening: vi.fn() }),
        leave: vi.fn(),
    };
});

test('accumulates lines and tracks status for the matching operation', () => {
    const { result } = renderHook(() => useOperation(42));
    act(() => captured({ operationId: 42, kind: 'line', line: 'hello' }));
    act(() => captured({ operationId: 42, kind: 'status', status: 'running', exitCode: null }));
    expect(result.current.lines).toEqual(['hello']);
    expect(result.current.status).toBe('running');
});

test('ignores events for a different operation id', () => {
    const { result } = renderHook(() => useOperation(42));
    act(() => captured({ operationId: 99, kind: 'line', line: 'nope' }));
    expect(result.current.lines).toEqual([]);
});
