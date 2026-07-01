import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import CreateDatabaseForm from './CreateDatabaseForm';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { name_suffix: '', db_user_suffix: '', db_pass: '', engine: '', charset: '', collation: '' },
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        reset: vi.fn(),
        clearErrors: vi.fn(),
        errors: {},
        transform: vi.fn(),
    }),
    usePage: () => ({ props: { auth: { user: { username: 'testuser', database_limit: 5 } } } }),
}));

// Mock axios
vi.mock('axios');
import axios from 'axios';

// Mock route() global
global.route = (name, params) => {
    const routes = {
        'databases.engine-options': '/databases/engine-options',
        'databases.store': '/databases',
    };
    if (params) {
        return routes[name] + '?' + new URLSearchParams(params).toString();
    }
    return routes[name] || `/${name}`;
};

beforeEach(() => {
    vi.clearAllMocks();
});

test('engine selector populates from engine-options response', async () => {
    axios.get = vi.fn().mockResolvedValue({
        data: { engines: ['mysql', 'postgres'], capabilities: null },
    });

    render(<CreateDatabaseForm />);

    // Open the modal
    const createBtn = screen.getByRole('button', { name: /create database/i });
    await userEvent.click(createBtn);

    await waitFor(() => {
        expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('/databases/engine-options'));
    });

    await waitFor(() => {
        expect(screen.getByRole('option', { name: /mysql/i })).toBeInTheDocument();
        expect(screen.getByRole('option', { name: /postgres/i })).toBeInTheDocument();
    });
});

test('selecting MySQL triggers re-fetch with ?engine=mysql and renders charset/collation fields', async () => {
    // Initial fetch: list of engines
    axios.get = vi.fn()
        .mockResolvedValueOnce({
            data: { engines: ['mysql', 'postgres'], capabilities: null },
        })
        .mockResolvedValueOnce({
            data: {
                engines: ['mysql', 'postgres'],
                capabilities: { label: 'MySQL', hasUsers: true, optionFields: ['charset', 'collation'] },
            },
        });

    render(<CreateDatabaseForm />);

    const createBtn = screen.getByRole('button', { name: /create database/i });
    await userEvent.click(createBtn);

    await waitFor(() => {
        expect(screen.getByRole('option', { name: /mysql/i })).toBeInTheDocument();
    });

    // Select mysql engine
    const engineSelect = screen.getByRole('combobox', { name: /engine/i });
    await userEvent.selectOptions(engineSelect, 'mysql');

    await waitFor(() => {
        expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('engine=mysql'));
    });

    await waitFor(() => {
        expect(screen.getByLabelText(/charset/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/collation/i)).toBeInTheDocument();
    });
});

test('selecting Postgres does not render charset/collation fields', async () => {
    axios.get = vi.fn()
        .mockResolvedValueOnce({
            data: { engines: ['mysql', 'postgres'], capabilities: null },
        })
        .mockResolvedValueOnce({
            data: {
                engines: ['mysql', 'postgres'],
                capabilities: { label: 'PostgreSQL', hasUsers: true, optionFields: ['encoding', 'locale'] },
            },
        });

    render(<CreateDatabaseForm />);

    const createBtn = screen.getByRole('button', { name: /create database/i });
    await userEvent.click(createBtn);

    await waitFor(() => {
        expect(screen.getByRole('option', { name: /postgres/i })).toBeInTheDocument();
    });

    const engineSelect = screen.getByRole('combobox', { name: /engine/i });
    await userEvent.selectOptions(engineSelect, 'postgres');

    await waitFor(() => {
        expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('engine=postgres'));
    });

    await waitFor(() => {
        expect(screen.queryByLabelText(/charset/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/collation/i)).not.toBeInTheDocument();
    });
});

test('empty engines array shows empty state message', async () => {
    axios.get = vi.fn().mockResolvedValue({
        data: { engines: [], capabilities: null },
    });

    render(<CreateDatabaseForm />);

    const createBtn = screen.getByRole('button', { name: /create database/i });
    await userEvent.click(createBtn);

    await waitFor(() => {
        expect(screen.getByText(/no database engine is currently active/i)).toBeInTheDocument();
    });
});
