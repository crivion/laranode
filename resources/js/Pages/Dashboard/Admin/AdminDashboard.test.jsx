import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import AdminDashboard from './AdminDashboard';

// Mock window.axios (used by TopProcesses)
const mockAxiosGet = vi.fn(() => Promise.resolve({ data: { sortBy: 'cpu' } }));
const mockAxiosPatch = vi.fn(() => Promise.resolve({ data: { sortBy: 'cpu' } }));

// Mock window.Echo
const mockWhisper = vi.fn();
const mockListen = vi.fn().mockReturnValue({ whisper: mockWhisper });
const mockLeave = vi.fn();

function makeEchoChannel() {
    return { listen: mockListen, whisper: mockWhisper };
}

beforeEach(() => {
    window.Echo = {
        private: vi.fn().mockReturnValue(makeEchoChannel()),
        leave: mockLeave,
    };
    window.axios = {
        get: mockAxiosGet,
        patch: mockAxiosPatch,
    };
    vi.clearAllMocks();
    // Re-assign after clearAllMocks
    mockAxiosGet.mockReturnValue(Promise.resolve({ data: { sortBy: 'cpu' } }));
    mockAxiosPatch.mockReturnValue(Promise.resolve({ data: { sortBy: 'cpu' } }));
    window.Echo = {
        private: vi.fn().mockReturnValue({ listen: vi.fn().mockReturnValue({}), whisper: vi.fn() }),
        leave: vi.fn(),
    };
    window.axios = {
        get: mockAxiosGet,
        patch: mockAxiosPatch,
    };
});

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ href, children }) => <a href={href}>{children}</a>,
}));

// Mock AuthenticatedLayout to just render children
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children, header }) => <div>{header}{children}</div>,
}));

// Minimal fixture matching shape AdminDashboard.jsx reads
const nonEmptyStats = {
    cpuStats: {
        usage: '42',
        loadTimes: '0.10, 0.05, 0.01',
        uptime: 'up 1 hour',
        processCount: '42',
    },
    memoryStats: { free: '1024', used: '512', buffcache: '256', total: '2048' },
    diskStats: { size: '100G', used: '20G', free: '80G', percent: '20%' },
    network: [],
    mysql: { pid: '123', memory: '64M', cpuTime: '0h1m', uptime: '2 days' },
    phpFpm: {},
    apache: { status: 'active', memory: '32M' },
};

describe('AdminDashboard', () => {
    it('shows CPU usage immediately when initialStats is non-empty (no spinner wait)', () => {
        render(<AdminDashboard initialStats={nonEmptyStats} />);
        // CPULive renders "42%" when cpuStats.usage is truthy
        expect(screen.getByText('42%')).toBeInTheDocument();
    });

    it('does not crash when initialStats is empty array', () => {
        expect(() => render(<AdminDashboard initialStats={[]} />)).not.toThrow();
    });

    it('does not crash when initialStats is undefined', () => {
        expect(() => render(<AdminDashboard />)).not.toThrow();
    });
});
