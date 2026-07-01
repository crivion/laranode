import { render, screen, act } from '@testing-library/react';
import { test, expect, vi, beforeEach } from 'vitest';
import OperationProgress from '@/Components/OperationProgress';

let captured;
vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 1 } } } }),
}));

beforeEach(() => {
    captured = null;
    window.Echo = {
        private: () => ({ listen: (_name, cb) => { captured = cb; }, stopListening: vi.fn() }),
        leave: vi.fn(),
    };
});

test('renders streamed lines and the terminal status', () => {
    const onDone = vi.fn();
    render(<OperationProgress operationId={5} onDone={onDone} />);
    act(() => captured({ operationId: 5, kind: 'line', line: 'building...' }));
    act(() => captured({ operationId: 5, kind: 'status', status: 'succeeded', exitCode: 0 }));
    expect(screen.getByText(/building\.\.\./)).toBeInTheDocument();
    expect(screen.getByText(/Status: succeeded/)).toBeInTheDocument();
    expect(onDone).toHaveBeenCalledWith('succeeded');
});
