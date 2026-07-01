import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import GpuLive from './GpuLive';

vi.mock('react-chartjs-2', () => ({
    Doughnut: () => <canvas data-testid="gpu-doughnut" />,
}));

vi.mock('chart.js', () => {
    const Chart = { register: () => {} };
    return { default: Chart, Chart, ArcElement: {}, Tooltip: {}, Legend: {} };
});

describe('GpuLive', () => {
    it('renders nothing when no GPU was detected', () => {
        const { container } = render(<GpuLive gpu={null} />);
        expect(container).toBeEmptyDOMElement();
    });

    it('renders gauges and stat cards when a GPU is present', () => {
        render(
            <GpuLive
                gpu={{ vendor: 'nvidia', name: 'RTX 3080', util: 42, vramUsed: 1.5, vramTotal: 8, temp: 61, power: 120 }}
            />
        );

        expect(screen.getByText(/RTX 3080/)).toBeInTheDocument();
        expect(screen.getAllByTestId('gpu-doughnut')).toHaveLength(2); // util + VRAM
        expect(screen.getAllByText('42%').length).toBeGreaterThan(0);
        expect(screen.getAllByText(/1\.5 \/ 8 GB/).length).toBeGreaterThan(0);
        expect(screen.getByText(/61°C/)).toBeInTheDocument();
        expect(screen.getByText(/120 W/)).toBeInTheDocument();
    });
});
