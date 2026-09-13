import { Doughnut } from 'react-chartjs-2';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const COLORS = [
    '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6',
    '#ef4444', '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4',
    '#84cc16', '#a78bfa',
];

/**
 * Aggregates topStats by mainCmd.
 * Items where each command is under 1% of the total are merged into "Other".
 *
 * @param {Array} topStats
 * @param {'cpu'|'memory'} sortBy
 * @returns {{ labels: string[], values: number[] }}
 */
function aggregate(topStats, sortBy) {
    const byCmd = {};
    for (const item of topStats) {
        const val = parseFloat(sortBy === 'memory' ? item.mem : item.cpu) || 0;
        byCmd[item.mainCmd] = (byCmd[item.mainCmd] || 0) + val;
    }

    const labels = [];
    const values = [];
    let other = 0;

    for (const [cmd, val] of Object.entries(byCmd)) {
        if (val < 1) {
            other += val;
        } else {
            labels.push(cmd);
            values.push(val);
        }
    }

    if (other > 0) {
        labels.push('Other');
        values.push(Math.round(other * 100) / 100);
    }

    return { labels, values };
}

/**
 * Doughnut chart showing top processes by CPU or memory usage.
 *
 * @param {{ topStats: Array, sortBy: 'cpu'|'memory' }} props
 */
const TopProcessesChart = ({ topStats, sortBy = 'cpu' }) => {
    if (!topStats || topStats.length === 0) {
        return null;
    }

    const { labels, values } = aggregate(topStats, sortBy);
    const metricLabel = sortBy === 'memory' ? 'MEM' : 'CPU';

    const data = {
        labels,
        datasets: [
            {
                data: values,
                backgroundColor: labels.map((_, i) => COLORS[i % COLORS.length]),
                borderWidth: 1,
            },
        ],
    };

    const options = {
        responsive: true,
        // Derive height from width (aspect ratio) rather than the parent's
        // height. With maintainAspectRatio:false the chart mounts on the first
        // websocket payload — at which point the parent's height can momentarily
        // be 0 — and chart.js sizes to that and never re-measures a height
        // change, leaving the doughnut a tiny dot. Width is always non-zero and
        // chart.js does observe width changes, so this stays correctly sized.
        maintainAspectRatio: true,
        aspectRatio: 1.6,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const item = topStats.find((p) => p.mainCmd === ctx.label);
                        if (!item) return `${ctx.label} — ${ctx.parsed}% ${metricLabel}`;
                        return `${ctx.label} — ${item.cpu}% CPU / ${item.mem}% MEM`;
                    },
                },
            },
        },
    };

    return (
        <div className="relative w-full max-w-md mx-auto mt-3">
            <Doughnut data={data} options={options} />
        </div>
    );
};

export default TopProcessesChart;
