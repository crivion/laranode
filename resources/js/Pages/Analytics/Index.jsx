import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Line, Bar } from 'react-chartjs-2';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { TbChartBar } from 'react-icons/tb';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const MS_PER_DAY = 86400 * 1000;
const EXPIRY_WARNING_DAYS = 14;

function bytesToGb(bytes) {
    return (bytes / (1024 * 1024 * 1024)).toFixed(2);
}

function DiskTrendChart({ resourceHistory }) {
    const labels = resourceHistory.map((row) => row.snapshotted_at);
    const data = {
        labels,
        datasets: [
            {
                label: 'Disk (GB)',
                data: resourceHistory.map((row) => bytesToGb(row.disk_bytes)),
                borderColor: 'rgba(99, 102, 241, 1)',
                backgroundColor: 'rgba(99, 102, 241, 0.2)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
            },
        ],
    };
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
            x: { display: false },
            y: { display: true, beginAtZero: true },
        },
    };
    return <div style={{ height: 200 }} data-testid="disk-trend-chart"><Line data={data} options={options} /></div>;
}

function RequestTrendChart({ resourceHistory }) {
    const labels = resourceHistory.map((row) => row.snapshotted_at);
    const data = {
        labels,
        datasets: [
            {
                label: 'Apache Requests',
                data: resourceHistory.map((row) => row.apache_request_count ?? 0),
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
            },
        ],
    };
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
            x: { display: false },
            y: { display: true, beginAtZero: true },
        },
    };
    return <div style={{ height: 200 }} data-testid="request-trend-chart"><Line data={data} options={options} /></div>;
}

function SiteBreakdownChart({ siteStats }) {
    // Aggregate: latest disk_bytes per website_id
    const latest = {};
    siteStats.forEach((row) => {
        if (!latest[row.website_id] || row.snapshotted_at > latest[row.website_id].snapshotted_at) {
            latest[row.website_id] = row;
        }
    });
    const rows = Object.values(latest);
    const data = {
        labels: rows.map((r) => r.website?.url ?? `site-${r.website_id}`),
        datasets: [
            {
                label: 'Disk (GB)',
                data: rows.map((r) => bytesToGb(r.disk_bytes)),
                backgroundColor: 'rgba(99, 102, 241, 0.6)',
            },
        ],
    };
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true },
        },
    };
    return <div style={{ height: 220 }} data-testid="site-breakdown-chart"><Bar data={data} options={options} /></div>;
}

function QuotaBar({ label, used, limit }) {
    const pct = limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;
    return (
        <div className="mb-4" data-testid="quota-bar">
            <div className="flex justify-between text-sm mb-1">
                <span>{label}</span>
                <span>{used} / {limit ?? '∞'}</span>
            </div>
            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                <div
                    className="bg-indigo-500 h-3 rounded-full"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

function SslTable({ sslOverview }) {
    const now = Date.now();
    return (
        <table className="w-full text-sm" data-testid="ssl-table">
            <thead>
                <tr className="text-left text-gray-500 dark:text-gray-400">
                    <th className="pb-2 pr-4">Domain</th>
                    <th className="pb-2 pr-4">SSL</th>
                    <th className="pb-2">Expires</th>
                </tr>
            </thead>
            <tbody>
                {sslOverview.map((row) => {
                    const isExpiringSoon =
                        row.ssl_expires_at &&
                        new Date(row.ssl_expires_at) - now < EXPIRY_WARNING_DAYS * MS_PER_DAY;
                    return (
                        <tr key={row.id} className="border-t border-gray-100 dark:border-gray-800">
                            <td className="py-2 pr-4 font-medium">{row.url}</td>
                            <td className="py-2 pr-4">
                                {row.ssl_enabled ? (
                                    <span className="text-green-600 font-semibold">Enabled</span>
                                ) : (
                                    <span className="text-gray-400">Off</span>
                                )}
                            </td>
                            <td className="py-2">
                                {row.ssl_expires_at ? (
                                    <>
                                        <span>{row.ssl_expires_at}</span>
                                        {isExpiringSoon && (
                                            <span
                                                className="ml-2 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded"
                                                data-testid="ssl-expiry-warning"
                                            >
                                                Expiring soon
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    <span className="text-gray-400">—</span>
                                )}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

export default function AnalyticsIndex({ resourceHistory, siteStats, quotaSummary, sslOverview }) {
    const hasHistory = resourceHistory && resourceHistory.length > 0;
    const hasSiteStats = siteStats && siteStats.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                    <TbChartBar className="mr-2" />
                    Analytics
                </h2>
            }
        >
            <Head title="Analytics" />

            <div className="max-w-7xl mt-6 px-0 lg:px-4 space-y-6">

                {/* Resource Trends */}
                <div className="shadow-md rounded-lg bg-white dark:bg-gray-950 p-6 text-gray-700 dark:text-gray-300">
                    <h3 className="font-semibold text-gray-700 dark:text-gray-200 mb-4">Resource Trends</h3>
                    {!hasHistory ? (
                        <p className="text-gray-400 text-sm" data-testid="no-data-notice">No data yet — snapshots will appear after the first rollup job runs.</p>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p className="text-sm font-medium mb-2">Disk Usage (GB)</p>
                                <DiskTrendChart resourceHistory={resourceHistory} />
                            </div>
                            <div>
                                <p className="text-sm font-medium mb-2">Apache Requests</p>
                                <RequestTrendChart resourceHistory={resourceHistory} />
                            </div>
                        </div>
                    )}
                </div>

                {/* Per-site Breakdown */}
                <div className="shadow-md rounded-lg bg-white dark:bg-gray-950 p-6 text-gray-700 dark:text-gray-300">
                    <h3 className="font-semibold text-gray-700 dark:text-gray-200 mb-4">Per-site Disk Breakdown</h3>
                    {!hasSiteStats ? (
                        <p className="text-gray-400 text-sm">No per-site data yet.</p>
                    ) : (
                        <SiteBreakdownChart siteStats={siteStats} />
                    )}
                </div>

                {/* Quota Consumption */}
                <div className="shadow-md rounded-lg bg-white dark:bg-gray-950 p-6 text-gray-700 dark:text-gray-300">
                    <h3 className="font-semibold text-gray-700 dark:text-gray-200 mb-4">Quota Consumption</h3>
                    {quotaSummary && (
                        <>
                            <QuotaBar
                                label="Websites"
                                used={quotaSummary.websites_count}
                                limit={quotaSummary.websites_limit}
                            />
                            <QuotaBar
                                label="Databases"
                                used={quotaSummary.databases_count}
                                limit={quotaSummary.databases_limit}
                            />
                        </>
                    )}
                </div>

                {/* SSL Overview */}
                <div className="shadow-md rounded-lg bg-white dark:bg-gray-950 p-6 text-gray-700 dark:text-gray-300">
                    <h3 className="font-semibold text-gray-700 dark:text-gray-200 mb-4">SSL Overview</h3>
                    {sslOverview && sslOverview.length > 0 ? (
                        <SslTable sslOverview={sslOverview} />
                    ) : (
                        <p className="text-gray-400 text-sm">No websites found.</p>
                    )}
                </div>

            </div>
        </AuthenticatedLayout>
    );
}
