import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import InstallPHPForm from './InstallPHPForm';

// Mock toast
vi.mock('react-toastify', () => ({ toast: { error: vi.fn() } }));

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));

// Mock window.route
beforeEach(() => {
    window.route = vi.fn(() => '/php/install');
});

describe('InstallPHPForm', () => {
    it('disables the installed version option and shows (installed) label', () => {
        const installedVersions = [{ version: '8.4', status: 'active', enabled: true }];
        render(<InstallPHPForm installedVersions={installedVersions} />);

        // Open modal
        fireEvent.click(screen.getByText('Install New Version'));

        const opt84 = screen.getByRole('option', { name: 'PHP 8.4 (installed)' });
        expect(opt84).toBeDisabled();
        expect(opt84).toHaveAttribute('value', '8.4');

        // 8.3 should not be disabled
        const opt83 = screen.getByRole('option', { name: 'PHP 8.3' });
        expect(opt83).not.toBeDisabled();
    });

    it('all options enabled when installedVersions is empty', () => {
        render(<InstallPHPForm installedVersions={[]} />);

        fireEvent.click(screen.getByText('Install New Version'));

        const opts = screen.getAllByRole('option');
        // first option is the placeholder "Select a version"
        const versionOpts = opts.filter((o) => o.value !== '');
        versionOpts.forEach((o) => expect(o).not.toBeDisabled());
    });

    it('all options enabled when installedVersions defaults (no prop)', () => {
        render(<InstallPHPForm />);

        fireEvent.click(screen.getByText('Install New Version'));

        const opts = screen.getAllByRole('option');
        const versionOpts = opts.filter((o) => o.value !== '');
        versionOpts.forEach((o) => expect(o).not.toBeDisabled());
    });
});
