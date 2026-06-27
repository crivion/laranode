import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import Filemanager from './Filemanager';

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div data-testid="layout">{children}</div>,
}));

// Mock react-toastify
vi.mock('react-toastify', () => ({
    toast: vi.fn(),
    ToastContainer: () => null,
}));

// Mock react-file-icon
vi.mock('react-file-icon', () => ({
    FileIcon: () => <span>FileIcon</span>,
    defaultStyles: {},
}));

// Mock Filemanager child components (modals)
vi.mock('./Components/CreateFile', () => ({ default: () => null }));
vi.mock('./Components/EditFile', () => ({ default: () => null }));
vi.mock('./Components/DeleteFiles', () => ({ default: () => null }));
vi.mock('./Components/RenameFile', () => ({ default: () => null }));
vi.mock('./Components/UploadFile', () => ({ default: () => null }));

// Mock Breadcrumb so we can control it independently
vi.mock('./Components/Breadcrumb', () => ({
    default: ({ path, onNavigate }) => (
        <div data-testid="breadcrumb" data-path={path}>Breadcrumb</div>
    ),
}));

// Stub global fetch to return empty files at root (goBack=false → no breadcrumb)
global.fetch = vi.fn(() =>
    Promise.resolve({
        ok: true,
        body: {
            getReader: () => {
                let done = false;
                return {
                    read: () => {
                        if (done) return Promise.resolve({ value: undefined, done: true });
                        done = true;
                        const json = JSON.stringify({ files: [], goBack: false });
                        return Promise.resolve({
                            value: new TextEncoder().encode(json),
                            done: false,
                        });
                    },
                };
            },
        },
    })
);

// Stub window.axios used by pasteFiles
global.window = global.window || {};
global.axios = { patch: vi.fn() };

describe('Filemanager — D10 hint banner', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.clearAllMocks();
    });

    it('shows hint text on first render', async () => {
        render(<Filemanager />);

        // Wait for spinner to clear (fetch resolves)
        await waitFor(() =>
            expect(screen.queryByText('Loading files list...')).not.toBeInTheDocument()
        );

        expect(
            screen.getByText(/Double-click a folder to enter it/i)
        ).toBeInTheDocument();
    });

    it('dismiss button removes hint from DOM', async () => {
        render(<Filemanager />);

        await waitFor(() =>
            expect(screen.queryByText('Loading files list...')).not.toBeInTheDocument()
        );

        const dismissBtn = screen.getByRole('button', { name: /dismiss hint/i });
        fireEvent.click(dismissBtn);

        expect(
            screen.queryByText(/Double-click a folder to enter it/i)
        ).not.toBeInTheDocument();
    });

    it('dismiss sets localStorage key', async () => {
        const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

        render(<Filemanager />);

        await waitFor(() =>
            expect(screen.queryByText('Loading files list...')).not.toBeInTheDocument()
        );

        const dismissBtn = screen.getByRole('button', { name: /dismiss hint/i });
        fireEvent.click(dismissBtn);

        expect(setItemSpy).toHaveBeenCalledWith('laranode_fm_hint_dismissed', 'true');
    });

    it('does not show hint when localStorage already set to true', async () => {
        localStorage.setItem('laranode_fm_hint_dismissed', 'true');

        render(<Filemanager />);

        await waitFor(() =>
            expect(screen.queryByText('Loading files list...')).not.toBeInTheDocument()
        );

        expect(
            screen.queryByText(/Double-click a folder to enter it/i)
        ).not.toBeInTheDocument();
    });
});
