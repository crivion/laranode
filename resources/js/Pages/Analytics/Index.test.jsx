import { render, screen } from '@testing-library/react';
import { test, expect, vi, beforeEach } from 'vitest';
import AnalyticsIndex from './Index';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    usePage: () => ({ props: { auth: { user: { id: 1, role: 'user' } }, flash: {} } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

// Mock react-toastify
vi.mock('react-toastify', () => ({
    ToastContainer: () => null,
    toast: Object.assign(vi.fn(), { error: vi.fn(), success: vi.fn() }),
}));

// Mock react-icons
vi.mock('react-icons/tb', () => ({
    TbChartBar: () => <span>TbChartBar</span>,
}));

// Mock AuthenticatedLayout — render children + header directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children, header }) => (
        <div>
            <div data-testid="header">{header}</div>
            <div data-testid="content">{children}</div>
        </div>
    ),
}));

// Mock react-chartjs-2 — render identifiable containers instead of canvas
vi.mock('react-chartjs-2', () => ({
    Line: ({ data }) => <div data-testid="line-chart" data-labels={JSON.stringify(data.labels)} />,
    Bar: ({ data }) => <div data-testid="bar-chart" data-labels={JSON.stringify(data.labels)} />,
}));

// route() is not used in Index.jsx but AuthenticatedLayout might be mocked already
global.route = (name) => `/${name.replace('.', '/')}`;

// ---- Fixtures ----

const makeSnapshot = (id, snapshotted_at, disk_bytes, apache_request_count = null) => ({
    id,
    user_id: 1,
    snapshotted_at,
    disk_bytes,
    apache_request_count,
});

const makeSiteStat = (id, website_id, url, disk_bytes) => ({
    id,
    website_id,
    user_id: 1,
    // Mirror the eager-loaded `website:id,url` relation the API returns,
    // so the chart exercises the real r.website.url path (not a flat r.url).
    website: { id: website_id, url },
    snapshotted_at: '2026-06-27T00:00:00Z',
    disk_bytes,
});

const makeSslRow = (id, url, ssl_enabled, ssl_expires_at) => ({
    id,
    url,
    ssl_enabled,
    ssl_status: ssl_enabled ? 'active' : null,
    ssl_expires_at,
});

const defaultQuota = {
    websites_count: 2,
    websites_limit: 5,
    databases_count: 1,
    databases_limit: 3,
};

beforeEach(() => {
    vi.clearAllMocks();
});

// ---- Tests ----

test('Line chart renders when resourceHistory has data', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[makeSnapshot(1, '2026-06-26T00:00:00Z', 1073741824)]}
            siteStats={[]}
            quotaSummary={defaultQuota}
            sslOverview={[]}
        />,
    );
    expect(screen.getAllByTestId('line-chart').length).toBeGreaterThanOrEqual(1);
});

test('Bar chart renders when siteStats has data', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[makeSnapshot(1, '2026-06-26T00:00:00Z', 1073741824)]}
            siteStats={[makeSiteStat(1, 10, 'example.com', 512000000)]}
            quotaSummary={defaultQuota}
            sslOverview={[]}
        />,
    );
    expect(screen.getByTestId('bar-chart')).toBeInTheDocument();
});

test('"No data yet" notice renders when resourceHistory is empty', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[]}
            siteStats={[]}
            quotaSummary={defaultQuota}
            sslOverview={[]}
        />,
    );
    expect(screen.getByTestId('no-data-notice')).toBeInTheDocument();
    expect(screen.queryAllByTestId('line-chart')).toHaveLength(0);
});

test('SSL table shows domain and expiry date', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[]}
            siteStats={[]}
            quotaSummary={defaultQuota}
            sslOverview={[makeSslRow(1, 'mysite.com', true, '2026-09-01T00:00:00Z')]}
        />,
    );
    expect(screen.getByTestId('ssl-table')).toBeInTheDocument();
    expect(screen.getByText('mysite.com')).toBeInTheDocument();
    expect(screen.getByText('2026-09-01T00:00:00Z')).toBeInTheDocument();
});

test('Row with ssl_expires_at null does NOT render an expiry warning', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[]}
            siteStats={[]}
            quotaSummary={defaultQuota}
            sslOverview={[makeSslRow(1, 'nossl.com', false, null)]}
        />,
    );
    expect(screen.queryByTestId('ssl-expiry-warning')).not.toBeInTheDocument();
});

test('Row expiring within 14 days renders a warning indicator', () => {
    // 10 days from "now" — we use a real Date.now() call in the component,
    // so construct a date that is 10 days in the future.
    const tenDaysFromNow = new Date(Date.now() + 10 * 86400 * 1000).toISOString();
    render(
        <AnalyticsIndex
            resourceHistory={[]}
            siteStats={[]}
            quotaSummary={defaultQuota}
            sslOverview={[makeSslRow(1, 'soon.com', true, tenDaysFromNow)]}
        />,
    );
    expect(screen.getByTestId('ssl-expiry-warning')).toBeInTheDocument();
});

test('Quota bars show counts', () => {
    render(
        <AnalyticsIndex
            resourceHistory={[]}
            siteStats={[]}
            quotaSummary={{ websites_count: 2, websites_limit: 5, databases_count: 1, databases_limit: 3 }}
            sslOverview={[]}
        />,
    );
    const bars = screen.getAllByTestId('quota-bar');
    expect(bars.length).toBeGreaterThanOrEqual(2);
    // Check that the counts are rendered
    expect(screen.getByText('2 / 5')).toBeInTheDocument();
    expect(screen.getByText('1 / 3')).toBeInTheDocument();
});
