import { describe, expect, it } from 'vitest';
import { buildSearch, parseSearch } from '../utils/search';

describe('search grammar', () => {
  it('parses event filters + free text', () => {
    const r = parseSearch('source_ip:10.0.0.1 hostname:web-01 severity:critical failed login');
    expect(r.filters.source_ip).toBe('10.0.0.1');
    expect(r.filters.hostname).toBe('web-01');
    expect(r.free).toBe('failed login');
  });
  it('round-trips', () => {
    const s = buildSearch({ severity: 'high', mitre: 'T1110' }, 'brute');
    expect(s).toContain('severity:high');
    expect(parseSearch(s, 'alerts').filters.mitre).toBe('T1110');
  });
});
