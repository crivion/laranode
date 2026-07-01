import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import CronJobsIndex from './Index';
import CreateCronJobForm from './Partials/CreateCronJobForm';

// ── Inertia mock ─────────────────────────────────────────────────────────────
const mockRouterPost = vi.fn();
const mockRouterDelete = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    router: {
        post: (...args) => mockRouterPost(...args),
        delete: (...args) => mockRouterDelete(...args),
    },
    usePage: () => ({ props: { auth: { user: { id: 1, role: 'user', username: 'testuser' } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

// ── Layout mock ───────────────────────────────────────────────────────────────
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children, header }) => (
        <div>
            <div data-testid="header">{header}</div>
            <div data-testid="content">{children}</div>
        </div>
    ),
}));

// ── Icon mocks ────────────────────────────────────────────────────────────────
vi.mock('react-icons/md', () => ({ MdSchedule: () => <span>MdSchedule</span> }));
vi.mock('react-icons/ti', () => ({ TiDelete: () => <span>Delete</span> }));

// ── Toast mock ────────────────────────────────────────────────────────────────
vi.mock('react-toastify', () => ({
    toast: Object.assign(vi.fn(), { error: vi.fn(), success: vi.fn() }),
}));

// ── ConfirmationButton mock ───────────────────────────────────────────────────
vi.mock('@/Components/ConfirmationButton', () => ({
    default: ({ children, doAction }) => (
        <button onClick={doAction} data-testid="confirm-btn">
            {children}
        </button>
    ),
}));

// ── route() global ────────────────────────────────────────────────────────────
global.route = (name, params) => {
    const map = {
        'cron-jobs.store': '/cron-jobs',
        'cron-jobs.destroy': '/cron-jobs/delete',
        'cron-jobs.toggle': '/cron-jobs/toggle',
        'cron-jobs.index': '/cron-jobs',
    };
    if (params) return (map[name] ?? `/${name}`) + '/' + (params.cronJob ?? '');
    return map[name] ?? `/${name}`;
};

// ── Fixtures ──────────────────────────────────────────────────────────────────
const sampleJobs = [
    {
        id: 1,
        schedule: '* * * * *',
        command: 'php /home/testuser_ln/artisan inspire',
        label: 'My cron label',
        active: true,
    },
    {
        id: 2,
        schedule: '0 2 * * *',
        command: 'php /home/testuser_ln/artisan schedule:run',
        label: null,
        active: false,
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

// ── Index tests ───────────────────────────────────────────────────────────────

test('renders cron job rows with schedule, command, label and status', () => {
    render(<CronJobsIndex cronJobs={sampleJobs} />);

    // Row 1
    expect(screen.getByText('* * * * *')).toBeInTheDocument();
    expect(screen.getByText('php /home/testuser_ln/artisan inspire')).toBeInTheDocument();
    expect(screen.getByText('My cron label')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();

    // Row 2 — null label renders as em-dash
    expect(screen.getByText('0 2 * * *')).toBeInTheDocument();
    expect(screen.getByText('Paused')).toBeInTheDocument();
    expect(screen.getByText('—')).toBeInTheDocument();
});

test('renders add-form button (Add Cron Job submit button)', () => {
    render(<CronJobsIndex cronJobs={[]} />);

    expect(screen.getByRole('button', { name: /add cron job/i })).toBeInTheDocument();
});

test('shows empty state when no cron jobs', () => {
    render(<CronJobsIndex cronJobs={[]} />);

    expect(screen.getByText(/no cron jobs configured/i)).toBeInTheDocument();
});

test('delete button calls router.delete with correct route', async () => {
    render(<CronJobsIndex cronJobs={sampleJobs} />);

    const deleteButtons = screen.getAllByTestId('confirm-btn');
    // First job is id=1
    await userEvent.click(deleteButtons[0]);

    expect(mockRouterDelete).toHaveBeenCalledWith(
        '/cron-jobs/delete/1',
        expect.any(Object)
    );
});

test('toggle button calls router.post with correct route', async () => {
    render(<CronJobsIndex cronJobs={sampleJobs} />);

    // "Active" button for job id=1
    const activeBtn = screen.getByRole('button', { name: /pause cron job/i });
    await userEvent.click(activeBtn);

    expect(mockRouterPost).toHaveBeenCalledWith(
        '/cron-jobs/toggle/1',
        {},
        expect.any(Object)
    );
});

// ── CreateCronJobForm tests ───────────────────────────────────────────────────

test('submitting the form calls router.post with schedule, command and label', async () => {
    render(<CreateCronJobForm />);

    // Default schedule preset is '* * * * *'
    const commandInput = screen.getByLabelText(/cron command/i);
    const labelInput = screen.getByLabelText(/cron label/i);
    const submitBtn = screen.getByRole('button', { name: /add cron job/i });

    await userEvent.type(commandInput, 'php /home/testuser_ln/artisan schedule:run');
    await userEvent.type(labelInput, 'Scheduler');
    await userEvent.click(submitBtn);

    expect(mockRouterPost).toHaveBeenCalledWith(
        '/cron-jobs',
        {
            schedule: '* * * * *',
            command: 'php /home/testuser_ln/artisan schedule:run',
            label: 'Scheduler',
        },
        expect.objectContaining({ onError: expect.any(Function) })
    );
});

test('selecting Custom… reveals the custom expression input', async () => {
    render(<CreateCronJobForm />);

    const scheduleSelect = screen.getByLabelText(/schedule preset/i);
    await userEvent.selectOptions(scheduleSelect, 'custom');

    expect(screen.getByLabelText(/custom cron expression/i)).toBeInTheDocument();
});

test('custom expression is sent to router.post when Custom preset used', async () => {
    render(<CreateCronJobForm />);

    const scheduleSelect = screen.getByLabelText(/schedule preset/i);
    await userEvent.selectOptions(scheduleSelect, 'custom');

    const customInput = screen.getByLabelText(/custom cron expression/i);
    await userEvent.type(customInput, '30 4 * * 0');

    const commandInput = screen.getByLabelText(/cron command/i);
    await userEvent.type(commandInput, 'php /home/testuser_ln/artisan inspire');

    await userEvent.click(screen.getByRole('button', { name: /add cron job/i }));

    expect(mockRouterPost).toHaveBeenCalledWith(
        '/cron-jobs',
        expect.objectContaining({ schedule: '30 4 * * 0' }),
        expect.objectContaining({ onError: expect.any(Function) })
    );
});

test('onError callback populates field-level error messages', async () => {
    render(<CreateCronJobForm />);

    await userEvent.click(screen.getByRole('button', { name: /add cron job/i }));

    // Grab the onError callback passed to router.post and invoke it
    const [, , options] = mockRouterPost.mock.calls[0];
    act(() => {
        options.onError({ schedule: 'Invalid cron expression.', command: 'Command not allowed.' });
    });

    await waitFor(() => {
        expect(screen.getByText('Invalid cron expression.')).toBeInTheDocument();
        expect(screen.getByText('Command not allowed.')).toBeInTheDocument();
    });
});
