import { render, screen } from '@testing-library/react';
import DbEnginesLive from './DbEnginesLive';

const mysqlStats = { memory: '64M', cpuTime: '0h1m', uptime: '2 days', pid: '123' };
const postgresStats = { memory: '128M', cpuTime: '0h2m', uptime: '1 day', pid: '456' };

describe('DbEnginesLive', () => {
    it('renders MySQL card when dbEngines has mysql key', () => {
        render(<DbEnginesLive dbEngines={{ mysql: mysqlStats }} />);
        expect(screen.getByText('MySQL')).toBeInTheDocument();
        expect(screen.getByText('64M')).toBeInTheDocument();
    });

    it('renders Postgres card when dbEngines has postgres key', () => {
        render(<DbEnginesLive dbEngines={{ postgres: postgresStats }} />);
        expect(screen.getByText('Postgres')).toBeInTheDocument();
        expect(screen.getByText('128M')).toBeInTheDocument();
    });

    it('renders nothing when dbEngines is empty object', () => {
        const { container } = render(<DbEnginesLive dbEngines={{}} />);
        expect(container.firstChild).toBeNull();
    });

    it('renders nothing when dbEngines is undefined', () => {
        const { container } = render(<DbEnginesLive dbEngines={undefined} />);
        expect(container.firstChild).toBeNull();
    });

    it('renders both MySQL and Postgres cards when both are present', () => {
        render(<DbEnginesLive dbEngines={{ mysql: mysqlStats, postgres: postgresStats }} />);
        expect(screen.getByText('MySQL')).toBeInTheDocument();
        expect(screen.getByText('Postgres')).toBeInTheDocument();
    });
});
