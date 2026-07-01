import { renderHook, act } from '@testing-library/react';
import { test, expect, vi, beforeEach } from 'vitest';
import useNotifications from '@/hooks/useNotifications';

let capturedListeners;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: { id: 3 } },
            notifications: { unreadCount: 2 },
        },
    }),
}));

beforeEach(() => {
    capturedListeners = {};
    window.Echo = {
        private: () => ({
            listen: (event, cb) => {
                capturedListeners[event] = cb;
                return {
                    listen: (e2, cb2) => { capturedListeners[e2] = cb2; },
                    stopListening: vi.fn(),
                };
            },
            stopListening: vi.fn(),
        }),
        leave: vi.fn(),
    };
});

test('initializes unreadCount from shared prop', () => {
    const { result } = renderHook(() => useNotifications());
    expect(result.current.unreadCount).toBe(2);
});

test('.NotificationCreated event updates unreadCount', () => {
    const { result } = renderHook(() => useNotifications());
    act(() => {
        capturedListeners['.NotificationCreated']({ unread_count: 5 });
    });
    expect(result.current.unreadCount).toBe(5);
});

test('refresh resets unreadCount to 0', () => {
    const { result } = renderHook(() => useNotifications());
    act(() => {
        capturedListeners['.NotificationCreated']({ unread_count: 7 });
    });
    expect(result.current.unreadCount).toBe(7);
    act(() => {
        result.current.refresh();
    });
    expect(result.current.unreadCount).toBe(0);
});
