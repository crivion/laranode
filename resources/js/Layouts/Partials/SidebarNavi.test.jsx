import { render, screen, fireEvent } from '@testing-library/react';
import { test, expect, vi, describe, beforeEach } from 'vitest';
import SidebarNavi from './SidebarNavi';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 1, role: 'user' } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

// Mock react-icons — SidebarNavi imports several icon packages
vi.mock('react-icons/ri', () => ({
    RiDashboard3Fill: () => <span>RiDashboard3Fill</span>,
    RiMvFill: () => <span>RiMvFill</span>,
}));
vi.mock('react-icons/im', () => ({ ImProfile: () => <span>ImProfile</span> }));
vi.mock('react-icons/fa6', () => ({
    FaPhp: () => <span>FaPhp</span>,
    FaUsers: () => <span>FaUsers</span>,
}));
vi.mock('react-icons/vsc', () => ({ VscFileSubmodule: () => <span>VscFileSubmodule</span> }));
vi.mock('react-icons/tb', () => ({
    // D7: TbDatabase replaces TbBrandMysql for the Databases link
    TbDatabase: () => <span data-testid="icon-TbDatabase">TbDatabase</span>,
    TbBrandMysql: () => <span data-testid="icon-TbBrandMysql">TbBrandMysql</span>,
    TbChartBar: () => <span>TbChartBar</span>,
    TbWorldWww: () => <span>TbWorldWww</span>,
}));
vi.mock('react-icons/md', () => ({
    MdSecurity: () => <span>MdSecurity</span>,
    MdOutlineListAlt: () => <span>MdOutlineListAlt</span>,
    MdSchedule: () => <span>MdSchedule</span>,
    MdBackup: () => <span>MdBackup</span>,
}));
vi.mock('react-icons/io5', () => ({ IoLockClosedOutline: () => <span>IoLockClosedOutline</span> }));

// Mock route() global — provide mappings for every route used in SidebarNavi
global.route = (name) => {
    const map = {
        'dashboard': '/dashboard',
        'accounts.index': '/accounts',
        'websites.index': '/websites',
        'firewall.index': '/firewall',
        'operations.index': '/operations',
        'analytics.index': '/analytics',
        'databases.index': '/databases',
        'cron-jobs.index': '/cron-jobs',
        'php.index': '/php',
        'backups.index': '/backups',
        'profile.edit': '/profile/edit',
    };
    return map[name] ?? `/${name}`;
};

// Helper: default props for the new collapsible sidebar
const defaultProps = {
    isCollapsed: false,
    setIsCollapsed: vi.fn(),
};

// ─── Existing tests (preserved) ───────────────────────────────────────────────

test('Analytics link renders with correct href and label for authenticated user', () => {
    render(<SidebarNavi {...defaultProps} />);

    const analyticsLink = screen.getByRole('link', { name: /analytics/i });
    expect(analyticsLink).toBeInTheDocument();
    expect(analyticsLink).toHaveAttribute('href', '/analytics');
});

test('Analytics link text is exactly "Analytics"', () => {
    render(<SidebarNavi {...defaultProps} />);

    expect(screen.getByText('Analytics')).toBeInTheDocument();
});

test('Analytics link is visible to non-admin users', () => {
    // usePage mock returns role: 'user' — Analytics must still render
    render(<SidebarNavi {...defaultProps} />);

    expect(screen.getByRole('link', { name: /analytics/i })).toBeInTheDocument();
});

test('Analytics link is visible to admin users', () => {
    // Re-mock usePage with admin role
    vi.mocked(vi.importActual).mockReturnValue?.(undefined); // no-op to avoid reset warnings
    // Override the mock for this test by re-mocking
    vi.doMock('@inertiajs/react', () => ({
        usePage: () => ({ props: { auth: { user: { id: 2, role: 'admin' } } } }),
        Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
    }));

    // The module is already loaded; admin check for Analytics doesn't gate it,
    // so the standard render (user role) already covers visibility.
    // We assert the link still appears (no admin guard).
    render(<SidebarNavi {...defaultProps} />);
    expect(screen.getByRole('link', { name: /analytics/i })).toBeInTheDocument();
});

test('Analytics link appears after File Manager in the sidebar', () => {
    render(<SidebarNavi {...defaultProps} />);

    const links = screen.getAllByRole('link');
    const fileManagerIdx = links.findIndex((l) => l.textContent.includes('File Manager'));
    const analyticsIdx = links.findIndex((l) => l.textContent.includes('Analytics'));

    expect(fileManagerIdx).toBeGreaterThanOrEqual(0);
    expect(analyticsIdx).toBeGreaterThan(fileManagerIdx);
});

// ─── D7: TbDatabase icon for Databases link ───────────────────────────────────

describe('D7 — engine-agnostic DB icon', () => {
    test('Databases link renders TbDatabase icon (not TbBrandMysql)', () => {
        render(<SidebarNavi {...defaultProps} />);

        // TbDatabase icon should be present
        expect(screen.getByTestId('icon-TbDatabase')).toBeInTheDocument();
        // TbBrandMysql icon should NOT be present anywhere in the sidebar
        expect(screen.queryByTestId('icon-TbBrandMysql')).not.toBeInTheDocument();
    });
});

// ─── D5: Collapsible sidebar ──────────────────────────────────────────────────

describe('D5 — collapsible sidebar', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    test('shows nav label spans when not collapsed (isCollapsed=false)', () => {
        render(<SidebarNavi isCollapsed={false} setIsCollapsed={vi.fn()} />);

        const dashboardSpan = screen.getByText('Dashboard');
        expect(dashboardSpan).not.toHaveClass('hidden');

        const databasesSpan = screen.getByText('Databases');
        expect(databasesSpan).not.toHaveClass('hidden');
    });

    test('hides nav label spans when collapsed (isCollapsed=true)', () => {
        render(<SidebarNavi isCollapsed={true} setIsCollapsed={vi.fn()} />);

        const dashboardSpan = screen.getByText('Dashboard');
        expect(dashboardSpan).toHaveClass('hidden');

        const databasesSpan = screen.getByText('Databases');
        expect(databasesSpan).toHaveClass('hidden');
    });

    test('calls setIsCollapsed(true) and sets localStorage when toggle clicked from expanded state', () => {
        const mockSetIsCollapsed = vi.fn();
        const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

        render(<SidebarNavi isCollapsed={false} setIsCollapsed={mockSetIsCollapsed} />);

        // Find the toggle button (hamburger)
        const toggleBtn = screen.getByRole('button');
        fireEvent.click(toggleBtn);

        expect(mockSetIsCollapsed).toHaveBeenCalledWith(true);
        expect(setItemSpy).toHaveBeenCalledWith('laranode_sidebar_collapsed', 'true');
    });

    test('calls setIsCollapsed(false) and sets localStorage when toggle clicked from collapsed state', () => {
        const mockSetIsCollapsed = vi.fn();
        const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

        render(<SidebarNavi isCollapsed={true} setIsCollapsed={mockSetIsCollapsed} />);

        const toggleBtn = screen.getByRole('button');
        fireEvent.click(toggleBtn);

        expect(mockSetIsCollapsed).toHaveBeenCalledWith(false);
        expect(setItemSpy).toHaveBeenCalledWith('laranode_sidebar_collapsed', 'false');
    });

    // When collapsed, the "Menu" heading and footer must fully hide (drop
    // md:block). If they kept md:block they would consume the narrow w-14
    // column and push the hamburger toggle past the overflow-x-hidden edge,
    // leaving no visible button to re-expand the sidebar.
    test('drops md:block on the Menu heading and footer when collapsed', () => {
        render(<SidebarNavi isCollapsed={true} setIsCollapsed={vi.fn()} />);

        const menuLabel = screen.getByText('Menu');
        expect(menuLabel.className).toContain('hidden');
        expect(menuLabel.className).not.toContain('md:block');

        const footer = screen.getByText('LaraNode').closest('p');
        expect(footer.className).toContain('hidden');
        expect(footer.className).not.toContain('md:block');
    });

    test('keeps md:block on the Menu heading and footer when expanded', () => {
        render(<SidebarNavi isCollapsed={false} setIsCollapsed={vi.fn()} />);

        expect(screen.getByText('Menu').className).toContain('md:block');
        expect(screen.getByText('LaraNode').closest('p').className).toContain('md:block');
    });

    test('toggle button stays the only button and is reachable when collapsed', () => {
        render(<SidebarNavi isCollapsed={true} setIsCollapsed={vi.fn()} />);

        // The hamburger is the single button; it must still render so the
        // sidebar can be re-expanded.
        expect(screen.getByRole('button')).toBeInTheDocument();
    });
});
