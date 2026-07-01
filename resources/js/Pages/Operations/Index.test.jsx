import { render, screen } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import Index from './Index';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ href, children, className, ...rest }) => (
        <a href={href} className={className} {...rest}>
            {children}
        </a>
    ),
}));

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

global.route = (name, params) => `/${name.replace(/\./g, '/')}/${params ? Object.values(params).join('/') : ''}`;

const sampleOperations = {
    data: [
        {
            id: 1,
            created_at: '2026-06-27 10:00:00',
            user: { username: 'admin' },
            type: 'ssl.enable',
            target: 'example.com',
            status: 'succeeded',
            output: 'ok',
        },
    ],
    prev_page_url: null,
    next_page_url: '/operations?page=2',
    current_page: 1,
    last_page: 2,
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('Operations Index', () => {
    it('renders table headers', () => {
        render(<Index operations={sampleOperations} />);
        expect(screen.getByText('When')).toBeInTheDocument();
        expect(screen.getByText('Actor')).toBeInTheDocument();
        expect(screen.getByText('Type')).toBeInTheDocument();
        expect(screen.getByText('Target')).toBeInTheDocument();
        expect(screen.getByText('Status')).toBeInTheDocument();
    });

    it('renders the operation row with succeeded badge', () => {
        render(<Index operations={sampleOperations} />);
        expect(screen.getByText('example.com')).toBeInTheDocument();
        expect(screen.getByText('succeeded')).toBeInTheDocument();
    });

    it('renders Previous as disabled span when prev_page_url is null', () => {
        render(<Index operations={sampleOperations} />);
        const prev = screen.getByText('Previous');
        // Should be a span (no href) when disabled
        expect(prev.tagName).toBe('SPAN');
        expect(prev).toHaveClass('opacity-50');
    });

    it('renders Next as a Link (anchor) with href when next_page_url is present', () => {
        render(<Index operations={sampleOperations} />);
        const next = screen.getByText('Next');
        expect(next.tagName).toBe('A');
        expect(next).toHaveAttribute('href', '/operations?page=2');
    });

    it('thead tr has dark mode text and border classes', () => {
        render(<Index operations={sampleOperations} />);
        const thead = document.querySelector('thead tr');
        expect(thead).toBeTruthy();
        expect(thead.className).toContain('dark:text-gray-300');
        expect(thead.className).toContain('dark:border-gray-700');
    });

    it('page container has max-w-7xl class', () => {
        render(<Index operations={sampleOperations} />);
        // The container div should have max-w-7xl — query by class directly
        const container = document.querySelector('.max-w-7xl');
        expect(container).toBeTruthy();
        expect(container.className).toContain('max-w-7xl');
    });

    it('succeeded badge has dark mode classes', () => {
        render(<Index operations={sampleOperations} />);
        const badge = screen.getByText('succeeded');
        expect(badge.className).toContain('dark:bg-green-800');
        expect(badge.className).toContain('dark:text-green-200');
    });

    it('renders operation with failed status with correct dark badge', () => {
        const failedOps = {
            ...sampleOperations,
            data: [
                {
                    ...sampleOperations.data[0],
                    id: 2,
                    status: 'failed',
                },
            ],
        };
        render(<Index operations={failedOps} />);
        const badge = screen.getByText('failed');
        expect(badge.className).toContain('dark:bg-red-800');
        expect(badge.className).toContain('dark:text-red-200');
    });
});
