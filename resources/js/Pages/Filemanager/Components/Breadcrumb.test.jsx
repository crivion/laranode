import { render, screen, fireEvent } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach } from 'vitest';
import Breadcrumb from './Breadcrumb';

describe('Breadcrumb', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders 3 clickable buttons and 1 non-button span for /home/alice_ln/domains', () => {
        const onNavigate = vi.fn();
        render(<Breadcrumb path="/home/alice_ln/domains" onNavigate={onNavigate} />);

        // Buttons: /, home, alice_ln (all except last segment)
        const buttons = screen.getAllByRole('button');
        expect(buttons).toHaveLength(3);

        // Last segment is a span, not a button
        const lastSpan = screen.getByText('domains');
        expect(lastSpan.tagName).toBe('SPAN');
    });

    it('clicking home button calls onNavigate with /home', () => {
        const onNavigate = vi.fn();
        render(<Breadcrumb path="/home/alice_ln/domains" onNavigate={onNavigate} />);

        const homeBtn = screen.getByRole('button', { name: 'home' });
        fireEvent.click(homeBtn);

        expect(onNavigate).toHaveBeenCalledWith('/home');
    });

    it('clicking root / button calls onNavigate with /', () => {
        const onNavigate = vi.fn();
        render(<Breadcrumb path="/home/alice_ln/domains" onNavigate={onNavigate} />);

        const rootBtn = screen.getByRole('button', { name: '/' });
        fireEvent.click(rootBtn);

        expect(onNavigate).toHaveBeenCalledWith('/');
    });

    it('last segment (domains) is not a button', () => {
        const onNavigate = vi.fn();
        render(<Breadcrumb path="/home/alice_ln/domains" onNavigate={onNavigate} />);

        const lastSpan = screen.getByText('domains');
        expect(lastSpan.tagName).not.toBe('BUTTON');
        expect(lastSpan.tagName).toBe('SPAN');
    });

    it('renders root-only path with just the / span (no buttons)', () => {
        const onNavigate = vi.fn();
        render(<Breadcrumb path="/" onNavigate={onNavigate} />);

        // path='/' — segments are empty after filter, so only root segment
        // Root is the only segment → it's the last → rendered as span
        const spans = screen.getAllByText('/');
        expect(spans.length).toBeGreaterThan(0);
        const buttons = screen.queryAllByRole('button');
        expect(buttons).toHaveLength(0);
    });
});
