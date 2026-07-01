import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import BackupsIndex from './Index';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    router: {
        delete: vi.fn(),
        reload: vi.fn(),
    },
    usePage: () => ({ props: { auth: { user: { id: 1, role: 'user' } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

// Mock axios
vi.mock('axios');
import axios from 'axios';

// Mock react-toastify
vi.mock('react-toastify', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

// Mock react-icons
vi.mock('react-icons/md', () => ({ MdBackup: () => <span>MdBackup</span> }));
vi.mock('react-icons/ti', () => ({ TiDelete: () => <span>Delete</span> }));

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children, header }) => (
        <div>
            <div data-testid="header">{header}</div>
            <div data-testid="content">{children}</div>
        </div>
    ),
}));

// Mock OperationProgress so we can inspect it without Echo
vi.mock('@/Components/OperationProgress', () => ({
    default: ({ operationId, onDone }) => (
        <div data-testid="operation-progress" data-operation-id={operationId}>
            <span>Progress for {operationId}</span>
            <button onClick={() => onDone && onDone('succeeded')}>Finish</button>
        </div>
    ),
}));

// Mock route() global
global.route = (name, params) => {
    const map = {
        'backups.store': '/backups',
        'backups.destroy': '/backups/delete',
        'backups.restore': '/backups/restore',
        'backups.download': '/backups/download',
        'backups.schedules.destroy': '/backups/schedules/delete',
        'backups.index': '/backups',
    };
    return map[name] ?? `/${name}`;
};

const sampleBackups = {
    data: [
        {
            id: 1,
            type: 'db',
            target: 'mydb',
            storage: 'local',
            status: 'completed',
            size_bytes: 1048576,
            created_at: '2026-06-26 02:00:00',
            path: '1/db/mydb/2026-06-26-020000.sql.gz',
            disk_name: 'backups',
        },
        {
            id: 2,
            type: 'files',
            target: 'example.com',
            storage: 'local',
            status: 'pending',
            size_bytes: null,
            created_at: '2026-06-26 03:00:00',
            path: null,
            disk_name: 'backups',
        },
    ],
};

const sampleSchedules = [
    {
        id: 1,
        type: 'db',
        target: 'mydb',
        cron_expression: '0 2 * * *',
        retention_count: 7,
        last_run_at: null,
        enabled: true,
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

test('backup rows render with type, target, and status', () => {
    render(<BackupsIndex backups={sampleBackups} schedules={[]} />);

    // First row: db / mydb / completed
    expect(screen.getByText('db')).toBeInTheDocument();
    expect(screen.getByText('mydb')).toBeInTheDocument();
    expect(screen.getByText('completed')).toBeInTheDocument();

    // Second row: files / example.com / pending
    expect(screen.getByText('files')).toBeInTheDocument();
    expect(screen.getByText('example.com')).toBeInTheDocument();
    expect(screen.getByText('pending')).toBeInTheDocument();
});

test('on-demand backup form submit calls axios and renders OperationProgress', async () => {
    axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 42 } });

    render(<BackupsIndex backups={{ data: [] }} schedules={[]} />);

    // Fill target
    const targetInput = screen.getByPlaceholderText(/database name/i);
    await userEvent.clear(targetInput);
    await userEvent.type(targetInput, 'mydb');

    // Submit the form
    const runBtn = screen.getByRole('button', { name: /run backup/i });
    await userEvent.click(runBtn);

    await waitFor(() => {
        expect(axios.post).toHaveBeenCalledWith('/backups', expect.objectContaining({ target: 'mydb' }));
    });

    await waitFor(() => {
        expect(screen.getByTestId('operation-progress')).toBeInTheDocument();
        expect(screen.getByText('Progress for 42')).toBeInTheDocument();
    });
});

test('restore button opens modal with new_target input and warning about original not touched', async () => {
    render(<BackupsIndex backups={sampleBackups} schedules={[]} />);

    // Click "Restore" for the completed db backup (row 1)
    const restoreBtn = screen.getByRole('button', { name: /restore/i });
    await userEvent.click(restoreBtn);

    // Modal should appear with labelled new_target input
    expect(screen.getByLabelText(/new target name/i)).toBeInTheDocument();

    // Warning text must contain "original is not touched"
    expect(screen.getByText(/original is not touched/i)).toBeInTheDocument();
});
