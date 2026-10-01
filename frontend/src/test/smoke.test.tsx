import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Login from '../pages/Login';
import { AuthProvider } from '../store/auth';

describe('cipherSOC frontend', () => {
  it('renders login branding', () => {
    render(<MemoryRouter><AuthProvider><Login /></AuthProvider></MemoryRouter>);
    expect(screen.getByText(/cipherSOC/)).toBeTruthy();
    expect(screen.getByTestId('email')).toBeTruthy();
  });
  it('alert status workflow constants are sane', () => {
    const statuses = ['new','acknowledged','investigating','escalated','resolved','closed','false_positive'];
    expect(statuses).toContain('acknowledged');
    expect(new Set(statuses).size).toBe(statuses.length);
  });
});
