import { render, screen, fireEvent } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';

const post = vi.fn();
const reload = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 1 } }, flash: {} } }),
    router: { post: (...a) => post(...a), reload: (...a) => reload(...a) },
    Head: ({ title }) => <title>{title}</title>,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ header, children }) => <div>{header}{children}</div>,
}));

// CreateFirewallRuleForm is a complex modal — out of scope here
vi.mock('./Partials/CreateFirewallRuleForm', () => ({ default: () => null }));

// ConfirmationButton: render a button that fires doAction immediately
vi.mock('@/Components/ConfirmationButton', () => ({
    default: ({ doAction, children }) => (
        <button data-testid="confirm-toggle" onClick={doAction}>{children}</button>
    ),
}));

vi.mock('react-toastify', () => ({ toast: vi.fn() }));

// route() returns the name so assertions can match on it
global.route = (name) => name;

import FirewallIndex from './Index';

const unsafe = {
    panelPort: 80,
    coversSsh: false,
    coversWeb: false,
    missing: ['SSH (port 22) — you would lose remote access to the server'],
    detectedIp: '203.0.113.9',
};

const safe = { panelPort: 80, coversSsh: true, coversWeb: true, missing: [], detectedIp: '203.0.113.9' };

describe('Firewall/Index lockout protection', () => {
    beforeEach(() => {
        post.mockClear();
        reload.mockClear();
    });

    it('shows the lockout warning + Safe Setup when disabled and unprotected', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={unsafe} />);

        expect(screen.getByText(/would lock you out/i)).toBeInTheDocument();
        expect(screen.getByText(/Safe Setup \(allow SSH, HTTP, HTTPS\)/i)).toBeInTheDocument();
    });

    it('Safe Setup posts to the safe-setup route', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={unsafe} />);

        fireEvent.click(screen.getByText(/Safe Setup \(allow SSH, HTTP, HTTPS\)/i));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('firewall.safe-setup');
        expect(post.mock.calls[0][1]).toEqual({}); // SSH from anywhere
    });

    it('offers an IP-restricted Safe Setup that sends ssh_from_ip', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={unsafe} />);

        fireEvent.click(screen.getByText(/restrict SSH to my IP/i));

        expect(post.mock.calls[0][0]).toBe('firewall.safe-setup');
        expect(post.mock.calls[0][1]).toEqual({ ssh_from_ip: '203.0.113.9' });
    });

    it('does NOT post toggle when enabling would lock the user out', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={unsafe} />);

        fireEvent.click(screen.getByTestId('confirm-toggle'));

        // blocked client-side; the only allowed post is Safe Setup, not toggle
        expect(post).not.toHaveBeenCalledWith('firewall.toggle', expect.anything(), expect.anything());
    });

    it('hides the warning when SSH and web are already covered', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={safe} />);

        expect(screen.queryByText(/would lock you out/i)).not.toBeInTheDocument();
    });

    it('allows enabling (posts toggle) once protections are in place', () => {
        render(<FirewallIndex status="inactive" rules={[]} safety={safe} />);

        fireEvent.click(screen.getByTestId('confirm-toggle'));

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('firewall.toggle');
        expect(post.mock.calls[0][1]).toEqual({ enabled: true });
    });
});
