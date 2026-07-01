import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import Notifications from './Notifications';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    usePage: () => ({
        props: { auth: { user: { id: 1 } } },
    }),
}));

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

// Mock axios
vi.mock('axios');
import axios from 'axios';

const EVENT_TYPES = ['operation.finished', 'ssl.expiring'];
const CHANNELS = ['database', 'mail', 'webhook'];

const samplePreferences = [
    { event_type: 'operation.finished', channel: 'database', enabled: true },
    { event_type: 'operation.finished', channel: 'mail', enabled: false },
    { event_type: 'ssl.expiring', channel: 'webhook', enabled: false },
];

beforeEach(() => {
    vi.clearAllMocks();
});

test('renders preference matrix with channel labels In-app, Email, Webhook', () => {
    render(
        <Notifications
            eventTypes={EVENT_TYPES}
            channels={CHANNELS}
            preferences={[]}
            webhookUrl=""
        />,
    );
    expect(screen.getByText('In-app')).toBeInTheDocument();
    expect(screen.getByText('Email')).toBeInTheDocument();
    expect(screen.getByText('Webhook')).toBeInTheDocument();
});

test('renders event type rows in the matrix', () => {
    render(
        <Notifications
            eventTypes={EVENT_TYPES}
            channels={CHANNELS}
            preferences={[]}
            webhookUrl=""
        />,
    );
    expect(screen.getByText('Operation finished')).toBeInTheDocument();
    expect(screen.getByText('SSL expiring')).toBeInTheDocument();
});

test('buildMatrix defaults to true (checked) when preference row is missing', () => {
    render(
        <Notifications
            eventTypes={['operation.finished']}
            channels={['database']}
            preferences={[]}
            webhookUrl=""
        />,
    );
    const checkboxes = screen.getAllByRole('checkbox');
    // Missing row → defaults to true → checkbox should be checked
    expect(checkboxes[0]).toBeChecked();
});

test('buildMatrix reflects disabled preference as unchecked checkbox', () => {
    render(
        <Notifications
            eventTypes={['operation.finished']}
            channels={['mail']}
            preferences={[
                { event_type: 'operation.finished', channel: 'mail', enabled: false },
            ]}
            webhookUrl=""
        />,
    );
    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes[0]).not.toBeChecked();
});

test('toggling a checkbox calls axios.patch /profile/notifications with correct payload', async () => {
    axios.patch = vi.fn().mockResolvedValue({ data: {} });

    render(
        <Notifications
            eventTypes={['operation.finished']}
            channels={['database']}
            preferences={[{ event_type: 'operation.finished', channel: 'database', enabled: true }]}
            webhookUrl=""
        />,
    );

    const checkbox = screen.getByRole('checkbox');
    expect(checkbox).toBeChecked();

    await userEvent.click(checkbox);

    await waitFor(() => {
        expect(axios.patch).toHaveBeenCalledWith('/profile/notifications', {
            event_type: 'operation.finished',
            channel: 'database',
            enabled: false,
        });
    });
});

test('toggle reverts to previous state when axios.patch fails', async () => {
    axios.patch = vi.fn().mockRejectedValue(new Error('Server error'));

    render(
        <Notifications
            eventTypes={['operation.finished']}
            channels={['database']}
            preferences={[{ event_type: 'operation.finished', channel: 'database', enabled: true }]}
            webhookUrl=""
        />,
    );

    const checkbox = screen.getByRole('checkbox');
    expect(checkbox).toBeChecked();

    await userEvent.click(checkbox);

    // After failure, should revert back to checked
    await waitFor(() => {
        expect(checkbox).toBeChecked();
    });
});

test('webhook URL input is pre-populated from webhookUrl prop (not auth.user)', () => {
    render(
        <Notifications
            eventTypes={[]}
            channels={[]}
            preferences={[]}
            webhookUrl="https://hooks.example.com/abc"
        />,
    );
    const input = screen.getByRole('textbox');
    expect(input).toHaveValue('https://hooks.example.com/abc');
});

test('webhook URL input is empty when webhookUrl prop is null', () => {
    render(
        <Notifications
            eventTypes={[]}
            channels={[]}
            preferences={[]}
            webhookUrl={null}
        />,
    );
    const input = screen.getByRole('textbox');
    expect(input).toHaveValue('');
});

test('saving a valid webhook URL calls axios.patch /profile/notifications/webhook and shows success', async () => {
    axios.patch = vi.fn().mockResolvedValue({ data: {} });

    render(
        <Notifications
            eventTypes={[]}
            channels={[]}
            preferences={[]}
            webhookUrl=""
        />,
    );

    const input = screen.getByRole('textbox');
    await userEvent.clear(input);
    await userEvent.type(input, 'https://hooks.slack.com/services/x');

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
        expect(axios.patch).toHaveBeenCalledWith('/profile/notifications/webhook', {
            webhook_url: 'https://hooks.slack.com/services/x',
        });
    });

    await waitFor(() => {
        expect(screen.getByText(/webhook url saved/i)).toBeInTheDocument();
    });
});

test('saving webhook URL shows error message when axios returns 422', async () => {
    axios.patch = vi.fn().mockRejectedValue({
        response: {
            data: {
                errors: {
                    webhook_url: ['The webhook url must be a valid URL with http or https scheme.'],
                },
            },
        },
    });

    render(
        <Notifications
            eventTypes={[]}
            channels={[]}
            preferences={[]}
            webhookUrl=""
        />,
    );

    const input = screen.getByRole('textbox');
    await userEvent.clear(input);
    await userEvent.type(input, 'ftp://bad.example.com');

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
        expect(
            screen.getByText(/the webhook url must be a valid URL with http or https scheme/i),
        ).toBeInTheDocument();
    });
});

test('saving webhook URL shows generic error when no specific error in response', async () => {
    axios.patch = vi.fn().mockRejectedValue(new Error('Network error'));

    render(
        <Notifications
            eventTypes={[]}
            channels={[]}
            preferences={[]}
            webhookUrl=""
        />,
    );

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
        expect(screen.getByText(/failed to save webhook url/i)).toBeInTheDocument();
    });
});

test('full matrix renders correct number of checkboxes', () => {
    render(
        <Notifications
            eventTypes={EVENT_TYPES}
            channels={CHANNELS}
            preferences={samplePreferences}
            webhookUrl=""
        />,
    );
    // 2 event types × 3 channels = 6 checkboxes
    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes).toHaveLength(6);
});
