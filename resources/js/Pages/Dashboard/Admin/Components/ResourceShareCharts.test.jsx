import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import ResourceShareCharts from './ResourceShareCharts';

const captured = [];

vi.mock('react-chartjs-2', () => ({
    Doughnut: ({ data }) => {
        captured.push(data);
        return <canvas data-testid="doughnut" />;
    },
}));

vi.mock('chart.js', () => {
    const Chart = { register: () => {} };
    return { default: Chart, Chart, ArcElement: {}, Tooltip: {}, Legend: {} };
});

const procs = [
    { mainCmd: 'mysqld', cpu: '10', mem: '12' },
    { mainCmd: 'php', cpu: '5', mem: '8' },
];

beforeEach(() => {
    captured.length = 0;
});

describe('ResourceShareCharts', () => {
    it('renders two doughnuts with Idle and Free slices when data is present', () => {
        render(
            <ResourceShareCharts
                topStats={procs}
                cpuStats={{ usage: '30' }}
                memoryStats={{ total: 16000, free: 8000 }}
            />
        );

        expect(screen.getAllByTestId('doughnut')).toHaveLength(2);
        const allLabels = captured.flatMap((d) => d.labels);
        expect(allLabels).toContain('Idle');
        expect(allLabels).toContain('Free');
        expect(allLabels).toContain('mysqld');
    });

    it('shows spinners (no canvas) until stats arrive', () => {
        render(<ResourceShareCharts topStats={[]} cpuStats={{}} memoryStats={{}} />);
        // memoryStats has no total -> mem model null -> spinner; cpu usage 0 still renders.
        // At minimum the memory card must not render a canvas.
        expect(screen.queryAllByTestId('doughnut').length).toBeLessThan(2);
    });
});
