import { render, screen, act, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { test, expect, vi, beforeEach } from 'vitest';
import Websites from './Index';

// ---- Mock @inertiajs/react ----
// NOTE: vi.mock is hoisted — do NOT reference top-level let/const variables here.
// Use vi.fn() inline; spy on the module after import for assertions.
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }) => <title>{title}</title>,
    router: {
        reload: vi.fn(),
        delete: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
    },
    usePage: () => ({ props: { auth: { user: { id: 1, role: 'user', domain_limit: 5 } } } }),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
}));

// Import router AFTER the mock so we get the mocked version
import { router } from '@inertiajs/react';

// ---- Mock axios ----
vi.mock('axios');
import axios from 'axios';

// ---- Mock react-toastify ----
vi.mock('react-toastify', () => ({
    toast: Object.assign(vi.fn(), { error: vi.fn(), success: vi.fn() }),
}));

// ---- Mock react-icons ----
vi.mock('react-icons/ti', () => ({ TiDelete: () => <span>Delete</span> }));
vi.mock('react-icons/tb', () => ({ TbWorldWww: ({ className }) => <span className={className}>World</span> }));
vi.mock('react-icons/md', () => ({
    MdLock: () => <span>Lock</span>,
    MdLockOpen: () => <span>LockOpen</span>,
}));
vi.mock('react-icons/fa', () => ({
    FaToggleOn: () => <span>ToggleOn</span>,
    FaToggleOff: () => <span>ToggleOff</span>,
}));

// ---- Mock AuthenticatedLayout ----
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children, header }) => (
        <div>
            <div data-testid="header">{header}</div>
            <div data-testid="content">{children}</div>
        </div>
    ),
}));

// ---- Mock ConfirmationButton ----
vi.mock('@/Components/ConfirmationButton', () => ({
    default: ({ children, doAction }) => (
        <span onClick={doAction}>{children}</span>
    ),
}));

// ---- Mock CreateWebsiteForm ----
vi.mock('./Partials/CreateWebsiteForm', () => ({
    default: () => <div data-testid="create-form" />,
}));

// ---- Mock route() global ----
global.route = (name, params) => {
    const map = {
        'php.get-versions': '/php/versions',
        'websites.runtime.switch': `/websites/${params?.website}/runtime`,
        'websites.ssl.toggle': `/websites/${params?.website}/ssl`,
        'websites.update': `/websites/${params?.website}`,
        'websites.destroy': `/websites/${params?.website}`,
    };
    return map[name] ?? `/${name}`;
};

// ---- Shared Echo mock captured callback ----
let capturedEchoCallback = null;

// ---- Base website fixtures ----
const makeFpmWebsite = (overrides = {}) => ({
    id: 1,
    url: 'example.com',
    ssl_enabled: false,
    ssl_status: null,
    fullDocumentRoot: '/home/example_ln/domains/example.com/public_html',
    document_root: '/public_html',
    php_version: { id: 1, version: '8.3' },
    runtime: 'php-fpm',
    runtime_port: null,
    runtime_label: 'PHP-FPM',
    user: { username: 'example', role: 'user' },
    ...overrides,
});

const makeFrankenWebsite = (overrides = {}) => makeFpmWebsite({
    runtime: 'frankenphp',
    runtime_port: 9100,
    runtime_label: 'FrankenPHP',
    ...overrides,
});

beforeEach(() => {
    vi.clearAllMocks();
    capturedEchoCallback = null;
    // Default Echo mock — captures the .OperationUpdated listener callback
    window.Echo = {
        private: () => ({
            listen: (_event, cb) => {
                capturedEchoCallback = cb;
            },
            stopListening: vi.fn(),
        }),
        leave: vi.fn(),
    };
    // Default axios behaviour
    axios.get = vi.fn().mockResolvedValue({ data: [{ id: 1, version: '8.3' }] });
    axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 99 } });
});

// ---- Test 1: runtime='frankenphp' → FrankenPHP badge; info banner visible ----
test('runtime=frankenphp shows FrankenPHP badge and info banner', () => {
    render(<Websites websites={[makeFrankenWebsite()]} serverIp="1.2.3.4" />);

    // Badge element specifically (not the <option> element)
    const badge = screen.getByTestId('runtime-badge-1');
    expect(badge).toHaveTextContent('FrankenPHP');
    expect(screen.getByTestId('frankenphp-info-banner')).toBeInTheDocument();
});

// ---- Test 2: runtime='php-fpm' → PHP-FPM badge; info banner absent ----
test('runtime=php-fpm shows PHP-FPM badge and no info banner', () => {
    render(<Websites websites={[makeFpmWebsite()]} serverIp="1.2.3.4" />);

    // Badge element specifically (not the <option> element)
    const badge = screen.getByTestId('runtime-badge-1');
    expect(badge).toHaveTextContent('PHP-FPM');
    expect(screen.queryByTestId('frankenphp-info-banner')).not.toBeInTheDocument();
});

// ---- Test 3: select FrankenPHP → axios.post called with correct route + body ----
test('selecting FrankenPHP calls axios.post with correct route and body', async () => {
    render(<Websites websites={[makeFpmWebsite()]} serverIp="1.2.3.4" />);

    const runtimeSelect = screen.getByTestId('runtime-select-1');
    await userEvent.selectOptions(runtimeSelect, 'frankenphp');

    await waitFor(() => {
        expect(axios.post).toHaveBeenCalledWith(
            '/websites/1/runtime',
            { runtime: 'frankenphp' },
        );
    });
});

// ---- Test 4: onDone via useEffect chain — Echo event → OperationProgress → router.reload() ----
test('onDone fires via Echo event chain: useOperation state → OperationProgress → router.reload()', async () => {
    axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 77 } });

    render(<Websites websites={[makeFpmWebsite()]} serverIp="1.2.3.4" />);

    // Trigger switchRuntime to set runtimeOp state
    const runtimeSelect = screen.getByTestId('runtime-select-1');
    await userEvent.selectOptions(runtimeSelect, 'frankenphp');

    // Wait for runtimeOp progress block to appear
    await waitFor(() => {
        expect(screen.getByTestId('runtime-op-progress')).toBeInTheDocument();
    });

    // Simulate Echo broadcasting a 'succeeded' status event (same shape useOperation expects)
    act(() => {
        capturedEchoCallback({ operationId: 77, kind: 'status', status: 'succeeded', exitCode: 0 });
    });

    // useEffect in OperationProgress fires onDone which calls router.reload()
    await waitFor(() => {
        expect(router.reload).toHaveBeenCalled();
    });
});

// ---- Test 5: runtime='frankenphp' → PHP version <select> is disabled ----
test('runtime=frankenphp disables PHP version select', () => {
    render(<Websites websites={[makeFrankenWebsite()]} serverIp="1.2.3.4" />);

    const phpSelect = screen.getByTestId('php-version-select-1');
    expect(phpSelect).toBeDisabled();
});

// ---- Test 6: runtime='php-fpm' → PHP version <select> NOT disabled ----
test('runtime=php-fpm does not disable PHP version select', () => {
    render(<Websites websites={[makeFpmWebsite()]} serverIp="1.2.3.4" />);

    const phpSelect = screen.getByTestId('php-version-select-1');
    expect(phpSelect).not.toBeDisabled();
});

// ---- Test 7: axios.post resolves → runtimeOp progress block rendered ----
test('axios.post resolves → runtimeOp progress block is rendered', async () => {
    axios.post = vi.fn().mockResolvedValue({ data: { operation_id: 55 } });

    render(<Websites websites={[makeFpmWebsite()]} serverIp="1.2.3.4" />);

    const runtimeSelect = screen.getByTestId('runtime-select-1');
    await userEvent.selectOptions(runtimeSelect, 'frankenphp');

    await waitFor(() => {
        expect(screen.getByTestId('runtime-op-progress')).toBeInTheDocument();
        expect(screen.getByText(/Switching example\.com to frankenphp/i)).toBeInTheDocument();
    });
});
