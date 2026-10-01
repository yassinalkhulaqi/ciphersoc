// Structured SOC search grammar: `source_ip:10.0.0.1 hostname:web-01 severity:critical free text`
// Mirrors backend App\Support\SearchParser.
export type SearchParse = { filters: Record<string, string>; free: string };

const EVENT_FIELDS = new Set(['event_type', 'severity', 'hostname', 'username', 'source_ip', 'destination_ip', 'source', 'parser', 'status']);
const ALERT_FIELDS = new Set(['severity', 'status', 'rule', 'mitre', 'ioc', 'hostname', 'source_ip']);

export function parseSearch(input: string, context: 'events' | 'alerts' = 'events'): SearchParse {
  const allowed = context === 'alerts' ? ALERT_FIELDS : EVENT_FIELDS;
  const filters: Record<string, string> = {};
  const free: string[] = [];
  const re = /(\w+):(?:"([^"]+)"|(\S+))|(\S+)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(input)) !== null) {
    if (m[1]) {
      const k = m[1].toLowerCase();
      const v = m[2] !== '' && m[2] !== undefined ? m[2] : (m[3] ?? '');
      if (allowed.has(k) && v !== '') {
        filters[k] = v;
        continue;
      }
      free.push(m[0]);
      continue;
    }
    free.push(m[4] ?? m[0]);
  }
  return { filters, free: free.join(' ').trim() };
}

export function buildSearch(filters: Record<string, string>, free = ''): string {
  const parts = Object.entries(filters).filter(([, v]) => v !== '').map(([k, v]) => (v.includes(' ') ? `${k}:"${v}"` : `${k}:${v}`));
  if (free.trim()) parts.push(free.trim());
  return parts.join(' ');
}
