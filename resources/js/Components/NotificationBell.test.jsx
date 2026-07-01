import { render, screen } from '@testing-library/react';
import { test, expect, vi } from 'vitest';
import NotificationBell from '@/Components/NotificationBell';

// Mock inertia Link and usePage so the component can render without a router
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }) => <a href={href}>{children}</a>,
    usePage: () => ({
        props: {
            auth: { user: { id: 1 } },
            notifications: { unreadCount: 0 },
        },
    }),
}));

// Mock Echo so useNotifications hook does not fail when no unreadCount prop supplied
beforeEach(() => {
    window.Echo = {
        private: () => ({
            listen: (_event, _cb) => ({ stopListening: vi.fn() }),
            stopListening: vi.fn(),
        }),
        leave: vi.fn(),
    };
});

test('renders no badge when unreadCount prop is 0', () => {
    render(<NotificationBell unreadCount={0} />);
    expect(screen.queryByTestId('notification-badge')).toBeNull();
});

test('renders badge with count when unreadCount prop is 3', () => {
    render(<NotificationBell unreadCount={3} />);
    const badge = screen.getByTestId('notification-badge');
    expect(badge).toBeInTheDocument();
    expect(badge).toHaveTextContent('3');
});
