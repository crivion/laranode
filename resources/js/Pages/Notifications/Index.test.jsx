import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import Index from './Index';

// Hoist mocks so variables are available inside vi.mock factories
const { mockReload, mockRefresh } = vi.hoisted(() => ({
    mockReload: vi.fn(),
    mockRefresh: vi.fn(),
}));

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    router: { reload: mockReload },
    usePage: () => ({
        props: {
            auth: { user: { id: 1 } },
            notifications: { unreadCount: 0 },
        },
    }),
    Link: ({ href, children }) => <a href={href}>{children}</a>,
}));

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

// Mock useNotifications hook
vi.mock('@/hooks/useNotifications', () => ({
    default: () => ({ unreadCount: 0, refresh: mockRefresh }),
}));

beforeEach(() => {
    vi.clearAllMocks();
    // Mock fetch globally
    global.fetch = vi.fn().mockResolvedValue({ ok: true });
    // Provide csrf token meta tag
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

const sampleNotifications = [
    {
        id: 'uuid-1',
        type: 'App\\Notifications\\OperationFinishedNotification',
        data: { title: 'Operation finished', message: 'SSL was installed.' },
        read_at: null,
        created_at: '2026-06-26 10:00:00',
    },
    {
        id: 'uuid-2',
        type: 'App\\Notifications\\SslExpiringNotification',
        data: { title: 'SSL expiring soon', message: 'Your cert expires in 7 days.' },
        read_at: '2026-06-26 11:00:00',
        created_at: '2026-06-25 10:00:00',
    },
];

test('renders notification list with titles and timestamps', () => {
    render(<Index notifications={sampleNotifications} />);
    expect(screen.getByText('Operation finished')).toBeInTheDocument();
    expect(screen.getByText('SSL was installed.')).toBeInTheDocument();
    expect(screen.getByText('SSL expiring soon')).toBeInTheDocument();
    expect(screen.getByText('2026-06-26 10:00:00')).toBeInTheDocument();
});

test('shows empty state when notification list is empty', () => {
    render(<Index notifications={[]} />);
    expect(screen.getByText(/no notifications yet/i)).toBeInTheDocument();
});

test('shows "Mark all as read" button when unread notifications exist', () => {
    render(<Index notifications={sampleNotifications} />);
    expect(screen.getByRole('button', { name: /mark all as read/i })).toBeInTheDocument();
});

test('does not show "Mark all as read" button when all notifications are read', () => {
    const allRead = sampleNotifications.map((n) => ({ ...n, read_at: '2026-06-26 12:00:00' }));
    render(<Index notifications={allRead} />);
    expect(screen.queryByRole('button', { name: /mark all as read/i })).toBeNull();
});

test('clicking "Mark all as read" calls fetch PATCH /notifications/read-all and refresh()', async () => {
    render(<Index notifications={sampleNotifications} />);

    const btn = screen.getByRole('button', { name: /mark all as read/i });
    await userEvent.click(btn);

    await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledWith(
            '/notifications/read-all',
            expect.objectContaining({ method: 'PATCH' }),
        );
    });

    await waitFor(() => {
        expect(mockRefresh).toHaveBeenCalled();
    });
});

test('clicking "Mark all as read" calls router.reload with only notifications', async () => {
    render(<Index notifications={sampleNotifications} />);

    const btn = screen.getByRole('button', { name: /mark all as read/i });
    await userEvent.click(btn);

    await waitFor(() => {
        expect(mockReload).toHaveBeenCalledWith({ only: ['notifications'] });
    });
});

test('shows individual "Mark read" button only for unread notifications', () => {
    render(<Index notifications={sampleNotifications} />);
    // sampleNotifications has 1 unread (uuid-1) and 1 read (uuid-2)
    const markReadButtons = screen.getAllByRole('button', { name: /mark read/i });
    expect(markReadButtons).toHaveLength(1);
});

test('clicking individual "Mark read" calls fetch PATCH /notifications/{id}/read', async () => {
    render(<Index notifications={sampleNotifications} />);

    const markReadBtn = screen.getByRole('button', { name: /^mark read$/i });
    await userEvent.click(markReadBtn);

    await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledWith(
            '/notifications/uuid-1/read',
            expect.objectContaining({ method: 'PATCH' }),
        );
    });

    await waitFor(() => {
        expect(mockReload).toHaveBeenCalledWith({ only: ['notifications'] });
    });
});
