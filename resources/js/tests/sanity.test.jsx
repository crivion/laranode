import { render, screen } from '@testing-library/react';
import { test, expect } from 'vitest';

test('vitest + React Testing Library render works', () => {
    render(<div>hello laranode</div>);
    expect(screen.getByText('hello laranode')).toBeInTheDocument();
});
