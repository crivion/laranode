import { describe, it, expect } from 'vitest';
import { aggregateByCommand, buildMemoryShare, buildCpuShare } from './processShares';

const procs = [
    { mainCmd: 'mysqld', cpu: '10', mem: '12' },
    { mainCmd: 'php', cpu: '5', mem: '8' },
    { mainCmd: 'php', cpu: '5', mem: '2' }, // same command aggregates
    { mainCmd: 'a', cpu: '1', mem: '1' },
    { mainCmd: 'b', cpu: '1', mem: '1' },
    { mainCmd: 'c', cpu: '1', mem: '1' },
    { mainCmd: 'd', cpu: '1', mem: '1' },
    { mainCmd: 'e', cpu: '1', mem: '1' }, // 8 commands -> beyond topN=6 -> Other
];

describe('aggregateByCommand', () => {
    it('sums duplicate commands and keeps only the top N, rest into other', () => {
        const { labels, values, other } = aggregateByCommand(procs, 'cpu', 6);
        // php is 5+5=10, ties mysqld at top
        expect(labels).toContain('php');
        expect(labels).toContain('mysqld');
        expect(labels.length).toBe(6);
        expect(other).toBeGreaterThan(0); // the 7th+8th commands
    });

    it('ignores zero/negative metric values', () => {
        const { labels } = aggregateByCommand([{ mainCmd: 'idle', cpu: '0', mem: '0' }], 'cpu');
        expect(labels).toHaveLength(0);
    });
});

describe('buildMemoryShare', () => {
    it('appends a Free slice from memoryStats and returns a GB center label', () => {
        const model = buildMemoryShare(procs, { total: 32000, free: 20000 });
        const free = model.slices.find((s) => s.label === 'Free');
        expect(free).toBeDefined();
        // free = 20000/32000 = 62.5%
        expect(free.value).toBeCloseTo(62.5, 1);
        expect(model.centerLabel).toMatch(/GB/);
    });

    it('returns null when total memory is unknown', () => {
        expect(buildMemoryShare(procs, { total: 0, free: 0 })).toBeNull();
    });
});

describe('buildCpuShare', () => {
    it('adds an Idle slice equal to 100 - usage and labels the center with usage%', () => {
        const model = buildCpuShare(procs, { usage: '40' });
        const idle = model.slices.find((s) => s.label === 'Idle');
        expect(idle.value).toBeCloseTo(60, 1);
        expect(model.centerLabel).toBe('40%');
    });

    it('scales per-core process percentages into the overall busy share (slices ~ 100%)', () => {
        const model = buildCpuShare(procs, { usage: '40' });
        const sum = model.slices.reduce((a, s) => a + s.value, 0);
        expect(sum).toBeCloseTo(100, 0);
    });
});
