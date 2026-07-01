/**
 * Pure helpers that turn the live top-process list into doughnut chart data:
 * top processes by a metric, an "Other" bucket, and a final remainder slice
 * (Free RAM / Idle CPU). Kept framework-free so it is unit-testable.
 */

export const SHARE_COLORS = [
    '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6',
];
export const OTHER_COLOR = '#9ca3af'; // gray-400
export const REMAINDER_COLOR = '#1f9d55'; // green — Free/Idle headroom

const round = (n) => Math.round(n * 100) / 100;

/**
 * Aggregate processes by command for a metric, keep the top N, merge the rest
 * into "Other".
 *
 * @param {Array} topStats  list of { mainCmd, cpu, mem }
 * @param {'cpu'|'mem'} metric
 * @param {number} topN
 * @returns {{ labels: string[], values: number[] }}
 */
export function aggregateByCommand(topStats, metric, topN = 6) {
    const byCmd = {};
    for (const item of topStats ?? []) {
        const val = parseFloat(metric === 'mem' ? item.mem : item.cpu) || 0;
        if (val <= 0) continue;
        byCmd[item.mainCmd] = (byCmd[item.mainCmd] || 0) + val;
    }

    const sorted = Object.entries(byCmd).sort((a, b) => b[1] - a[1]);
    const labels = [];
    const values = [];
    let other = 0;

    sorted.forEach(([cmd, val], i) => {
        if (i < topN) {
            labels.push(cmd);
            values.push(val);
        } else {
            other += val;
        }
    });

    return { labels, values, other };
}

/**
 * RAM doughnut: top processes by %MEM + Other used + Free.
 * Process %MEM is already a share of total RAM, so slices + Free ≈ 100%.
 *
 * @param {Array} topStats
 * @param {{ total:number, free:number }} memoryStats  MB
 */
export function buildMemoryShare(topStats, memoryStats) {
    const total = parseFloat(memoryStats?.total) || 0;
    const free = parseFloat(memoryStats?.free) || 0;
    if (total <= 0) return null;

    const freePct = Math.min(100, Math.max(0, (free / total) * 100));
    const usedPct = 100 - freePct;

    const { labels, values, other } = aggregateByCommand(topStats, 'mem');
    const topSum = values.reduce((a, b) => a + b, 0);
    // Whatever used memory isn't attributed to the top processes (incl. kernel,
    // buffers, untracked procs) becomes the Other-used slice.
    const otherUsed = Math.max(0, usedPct - topSum) + other;

    const slices = labels.map((l, i) => ({ label: l, value: round(values[i]), color: SHARE_COLORS[i % SHARE_COLORS.length] }));
    if (otherUsed > 0.1) slices.push({ label: 'Other used', value: round(otherUsed), color: OTHER_COLOR });
    slices.push({ label: 'Free', value: round(freePct), color: REMAINDER_COLOR });

    return {
        slices,
        centerLabel: `${round((total - free) / 1024)} / ${round(total / 1024)} GB`,
        centerSub: 'used',
    };
}

/**
 * CPU doughnut: top processes by %CPU (normalised into the overall busy share)
 * + Other busy + Idle. Process %CPU is per-core and can sum past 100, so scale
 * the process shares to fill the overall usage%, leaving a coherent Idle slice.
 *
 * @param {Array} topStats
 * @param {{ usage:number|string }} cpuStats  overall usage %
 */
export function buildCpuShare(topStats, cpuStats) {
    const usage = Math.min(100, Math.max(0, parseFloat(cpuStats?.usage) || 0));
    const idlePct = 100 - usage;

    const { labels, values, other } = aggregateByCommand(topStats, 'cpu');
    const procSum = values.reduce((a, b) => a + b, 0) + other;

    // Scale per-core process percentages into the real busy share.
    const scale = procSum > 0 ? usage / procSum : 0;

    const slices = labels.map((l, i) => ({
        label: l,
        value: round(values[i] * scale),
        color: SHARE_COLORS[i % SHARE_COLORS.length],
    }));
    const otherBusy = round(other * scale);
    if (otherBusy > 0.1) slices.push({ label: 'Other', value: otherBusy, color: OTHER_COLOR });
    slices.push({ label: 'Idle', value: round(idlePct), color: REMAINDER_COLOR });

    return {
        slices,
        centerLabel: `${round(usage)}%`,
        centerSub: 'busy',
    };
}
