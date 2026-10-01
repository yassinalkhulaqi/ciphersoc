import { describe, expect, it } from 'vitest';
import { parseSearch } from '../utils/search';

describe('playbook + correlation helpers', () => {
  it('builds matched vs skipped_sets', () => {
    const matched = [1, 2];
    const requested = [1, 2, 3];
    const skipped = requested.filter((x) => !matched.includes(x));
    expect(skipped).toEqual([3]);
  });
  it('sigma preview maps to native rule shape', () => {
    const r = parseSearch('severity:critical mitre:T1110 ssh', 'alerts');
    expect(r.filters.severity).toBe('critical');
    expect(r.filters.mitre).toBe('T1110');
  });
});
