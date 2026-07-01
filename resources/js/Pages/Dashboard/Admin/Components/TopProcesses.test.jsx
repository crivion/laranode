import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TopProcessesChart from './TopProcessesChart';

// Capture data passed to the Doughnut component so we can verify aggregation logic.
// We mock react-chartjs-2 to avoid the canvas/WebGL environment unavailable in jsdom.
let capturedChartData = null;
let capturedChartOptions = null;

vi.mock('react-chartjs-2', () => ({
    Doughnut: ({ data, options }) => {
        capturedChartData = data;
        capturedChartOptions = options;
        return <canvas data-testid="doughnut-chart" />;
    },
}));

// chart.js registers are no-ops in tests
vi.mock('chart.js', () => {
    const noop = () => {};
    const Chart = { register: noop, defaults: {} };
    return {
        default: Chart,
        Chart,
        ArcElement: {},
        Tooltip: {},
        Legend: {},
        DoughnutController: {},
    };
});

// Sample topStats with 12 processes, most under 1% CPU so "Other" is expected
const makeSample = (n = 12) =>
    Array.from({ length: n }, (_, i) => ({
        pid: String(i + 1),
        cpu: i === 0 ? '30.5' : '0.4', // first dominates, rest are under 1%
        mem: i === 0 ? '20.0' : '0.3',
        user: 'root',
        mainCmd: `proc${i}`,
        restOfCmd: [],
    }));

beforeEach(() => {
    capturedChartData = null;
    capturedChartOptions = null;
    vi.clearAllMocks();
});

describe('TopProcessesChart', () => {
    it('renders a canvas element when topStats has entries', () => {
        render(<TopProcessesChart topStats={makeSample()} sortBy="cpu" />);
        expect(screen.getByTestId('doughnut-chart')).toBeInTheDocument();
    });

    it('contains an "Other" label when many processes are each under 1%', () => {
        render(<TopProcessesChart topStats={makeSample(12)} sortBy="cpu" />);
        expect(capturedChartData).not.toBeNull();
        expect(capturedChartData.labels).toContain('Other');
    });

    it('renders nothing (no canvas) when topStats is empty', () => {
        render(<TopProcessesChart topStats={[]} sortBy="cpu" />);
        expect(screen.queryByTestId('doughnut-chart')).not.toBeInTheDocument();
    });

    it('sizes from width via a fixed aspect ratio, not the parent height', () => {
        // Regression guard: with maintainAspectRatio:false the chart mounts on
        // the first websocket payload while the parent height can be 0, sizes to
        // that, and never recovers — rendering a tiny dot. Deriving height from
        // width (maintainAspectRatio:true + aspectRatio) avoids the zero-height trap.
        render(<TopProcessesChart topStats={makeSample()} sortBy="cpu" />);
        expect(capturedChartOptions).not.toBeNull();
        expect(capturedChartOptions.maintainAspectRatio).toBe(true);
        expect(capturedChartOptions.aspectRatio).toBeGreaterThan(0);
        expect(capturedChartOptions.plugins.legend.position).toBe('bottom');
    });

    it('uses memory metric when sortBy is "memory"', () => {
        const stats = [
            { pid: '1', cpu: '5.0', mem: '40.0', user: 'root', mainCmd: 'bigmem', restOfCmd: [] },
            { pid: '2', cpu: '0.1', mem: '0.2', user: 'root', mainCmd: 'tiny', restOfCmd: [] },
        ];
        render(<TopProcessesChart topStats={stats} sortBy="memory" />);
        expect(capturedChartData).not.toBeNull();
        // bigmem should have the largest slice (40.0 vs 0.2)
        const bigmemIndex = capturedChartData.labels.indexOf('bigmem');
        expect(bigmemIndex).toBeGreaterThanOrEqual(0);
        expect(capturedChartData.datasets[0].data[bigmemIndex]).toBeCloseTo(40.0);
    });

    it('uses CPU metric when sortBy is "cpu" (default)', () => {
        const stats = [
            { pid: '1', cpu: '50.0', mem: '5.0', user: 'root', mainCmd: 'highcpu', restOfCmd: [] },
            { pid: '2', cpu: '0.1', mem: '0.2', user: 'root', mainCmd: 'low', restOfCmd: [] },
        ];
        render(<TopProcessesChart topStats={stats} sortBy="cpu" />);
        expect(capturedChartData).not.toBeNull();
        const highCpuIndex = capturedChartData.labels.indexOf('highcpu');
        expect(highCpuIndex).toBeGreaterThanOrEqual(0);
        expect(capturedChartData.datasets[0].data[highCpuIndex]).toBeCloseTo(50.0);
    });
});
