import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';

// Mock @inertiajs/react — usePage returns auth.user.id = 1
vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    Head: ({ title }) => <title>{title}</title>,
    usePage: () => ({
        props: {
            auth: { user: { id: 1 } },
            flash: {},
        },
    }),
    router: { delete: vi.fn() },
}));

// Mock AuthenticatedLayout — render children directly
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

// Mock partials — they're complex subforms, not under test here
vi.mock('./Partials/CreateAccountForm', () => ({
    default: () => <div />,
}));
vi.mock('./Partials/EditAccountForm', () => ({
    default: () => <div />,
}));
vi.mock('@/Components/ConfirmationButton', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

// Mock react-tooltip
vi.mock('react-tooltip', () => ({ Tooltip: () => <div /> }));

// Mock react-toastify
vi.mock('react-toastify', () => ({ toast: vi.fn() }));

// Mock window.route (Ziggy)
global.route = (name, params) => `/${name}/${JSON.stringify(params)}`;

import Accounts from './Index';

const accounts = [
    {
        id: 1,
        name: 'Admin User',
        username: 'admin',
        email: 'a@b.com',
        role: 'admin',
        ssh_access: false,
        domain_limit: null,
        database_limit: null,
    },
    {
        id: 2,
        name: 'Regular User',
        username: 'user2',
        email: 'u@b.com',
        role: 'user',
        ssh_access: false,
        domain_limit: null,
        database_limit: null,
    },
];

describe('Accounts/Index impersonate link visibility', () => {
    it('shows impersonate link for account id 2 (other user)', () => {
        render(<Accounts accounts={accounts} />);
        // The impersonate link for user 2 should be present
        // It has data-tooltip-content="Impersonate User" and wraps account id 2
        const links = screen.getAllByRole('link').filter(
            (el) => el.getAttribute('data-tooltip-content') === 'Impersonate User'
        );
        expect(links.length).toBe(1);
        // The remaining link should point to user 2, not user 1
        expect(links[0].getAttribute('href')).toContain('2');
    });

    it('hides impersonate link for own row (account.id === auth.user.id === 1)', () => {
        render(<Accounts accounts={accounts} />);
        const links = screen.getAllByRole('link').filter(
            (el) => el.getAttribute('data-tooltip-content') === 'Impersonate User'
        );
        // No link should reference user id 1
        const selfLink = links.find((el) => el.getAttribute('href') && el.getAttribute('href').includes('"user":1'));
        expect(selfLink).toBeUndefined();
    });
});
