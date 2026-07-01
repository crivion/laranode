import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach, describe } from 'vitest';
import DbServiceControl from './Partials/DbServiceControl';

// Mock OperationProgress as a simple stub
vi.mock('@/Components/OperationProgress', () => ({
    default: ({ operationId, onDone }) => (
        <div data-testid="op-progress" data-operation-id={operationId}>
            {operationId}
            <button data-testid="trigger-done" onClick={() => onDone && onDone('succeeded')}>
                done
            </button>
        </div>
    ),
}));

// Mock axios
vi.mock('axios');
import axios from 'axios';

// Mock route() global
global.route = (name) =>
    ({
        'databases.service.status': '/admin/databases/service/status',
        'databases.service.action': '/admin/databases/service',
    }[name]);

beforeEach(() => {
    vi.clearAllMocks();
});

describe('DbServiceControl', () => {
    test('renders status table with engine, service, active badge and action buttons', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { statuses: { mysql: { service: 'mysql', active: true } } },
        });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getAllByText('mysql').length).toBeGreaterThan(0);
        });

        expect(screen.getByText('active')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^start$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^stop$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^restart$/i })).toBeInTheDocument();
    });

    test('active badge has green class; inactive badge has red class', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: {
                statuses: {
                    mysql: { service: 'mysql', active: true },
                    postgres: { service: 'postgresql', active: false },
                },
            },
        });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getAllByText('mysql').length).toBeGreaterThan(0);
        });

        const activeBadge = screen.getByText('active');
        expect(activeBadge.className).toMatch(/green/);

        const inactiveBadge = screen.getByText('inactive');
        expect(inactiveBadge.className).toMatch(/red/);
    });

    test('dispatches action and renders OperationProgress with operationId', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { statuses: { mysql: { service: 'mysql', active: true } } },
        });
        axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 42 } });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^restart$/i })).toBeInTheDocument();
        });

        await userEvent.click(screen.getByRole('button', { name: /^restart$/i }));

        await waitFor(() => {
            expect(axios.post).toHaveBeenCalledWith('/admin/databases/service', {
                engine: 'mysql',
                action: 'restart',
            });
        });

        await waitFor(() => {
            expect(screen.getByTestId('op-progress')).toBeInTheDocument();
            expect(screen.getByTestId('op-progress').dataset.operationId).toBe('42');
        });
    });

    test('buttons disabled during operation (after click, before onDone)', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { statuses: { mysql: { service: 'mysql', active: true } } },
        });
        axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 99 } });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^restart$/i })).toBeInTheDocument();
        });

        await userEvent.click(screen.getByRole('button', { name: /^restart$/i }));

        await waitFor(() => {
            expect(screen.getByTestId('op-progress')).toBeInTheDocument();
        });

        // All action buttons should be disabled while operation is in flight
        expect(screen.getByRole('button', { name: /^start$/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /^stop$/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /^restart$/i })).toBeDisabled();
    });

    test('status re-fetched after onDone fires', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { statuses: { mysql: { service: 'mysql', active: true } } },
        });
        axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 55 } });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^restart$/i })).toBeInTheDocument();
        });

        await userEvent.click(screen.getByRole('button', { name: /^restart$/i }));

        await waitFor(() => {
            expect(screen.getByTestId('trigger-done')).toBeInTheDocument();
        });

        // axios.get called once on mount
        expect(axios.get).toHaveBeenCalledTimes(1);

        await userEvent.click(screen.getByTestId('trigger-done'));

        await waitFor(() => {
            expect(axios.get).toHaveBeenCalledTimes(2);
        });
    });

    test('error path: axios.post reject sets error message and buttons not disabled', async () => {
        axios.get = vi.fn().mockResolvedValue({
            data: { statuses: { mysql: { service: 'mysql', active: true } } },
        });
        axios.post = vi.fn().mockRejectedValue({
            response: { data: { message: 'Forbidden' } },
        });

        render(<DbServiceControl />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /^restart$/i })).toBeInTheDocument();
        });

        await userEvent.click(screen.getByRole('button', { name: /^restart$/i }));

        await waitFor(() => {
            expect(screen.getByText('Forbidden')).toBeInTheDocument();
        });

        // Buttons must NOT be permanently disabled after error
        expect(screen.getByRole('button', { name: /^start$/i })).not.toBeDisabled();
        expect(screen.getByRole('button', { name: /^stop$/i })).not.toBeDisabled();
        expect(screen.getByRole('button', { name: /^restart$/i })).not.toBeDisabled();
    });
});
