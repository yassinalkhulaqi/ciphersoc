import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { getPage } from '../api/client';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { Card, EmptyState, JsonView, Pagination, SeverityBadge, Skeleton } from '../components/ui';

export default function Events() {
  const [sp, setSp] = useSearchParams();
  const [page, setPage] = useState(1);
  const [eventType, setEventType] = useState('');
  const [severity, setSeverity] = useState('');
  const [search, setSearch] = useState(sp.get('search') ?? '');
  const debouncedSearch = useDebouncedValue(search, 350);
  const [open, setOpen] = useState<number | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['events', page, eventType, severity, debouncedSearch], queryFn: () => getPage<{ id: number; event_type: string; severity: string; message: string; hostname?: string; source_ip?: string; event_timestamp: string; raw_log?: string; normalized?: unknown }>('/events', { page, event_type: eventType || undefined, severity: severity || undefined, search: debouncedSearch || undefined }) });
  return (
    <Card title="Events / Logs" sub="Normalized telemetry — click a row for raw + normalized payload. Grammar: source_ip: hostname: severity: event_type: + free text.">
      <div className="filters">
        <input style={{ flex: 1 }} value={search} onChange={(e) => { setSearch(e.target.value); setSp(e.target.value ? { search: e.target.value } : {}); setPage(1); }} placeholder="source_ip:10.0.0.1 hostname:web-01 severity:critical failed password…" />
        <select value={eventType} onChange={(e) => setEventType(e.target.value)}><option value="">All types</option><option>authentication_failure</option><option>authentication_success</option><option>powershell_script</option><option>process_creation</option><option>firewall_deny</option><option>dns_query</option></select>
        <select value={severity} onChange={(e) => setSeverity(e.target.value)}><option value="">All severities</option><option>critical</option><option>high</option><option>medium</option><option>low</option><option>info</option></select>
      </div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No events" hint="Point the Python agent at the ingestion API to stream telemetry." /> : <><table className="tbl"><thead><tr><th>Time</th><th>Type</th><th>Severity</th><th>Host</th><th>Message</th></tr></thead><tbody>
        {data.items.map((e) => <><tr key={e.id} onClick={() => setOpen(open === e.id ? null : e.id)}><td className="muted">{new Date(e.event_timestamp).toLocaleString()}</td><td className="kv">{e.event_type}</td><td><SeverityBadge value={e.severity} /></td><td>{e.hostname ?? '—'}</td><td>{(e.message ?? '').slice(0, 110)}</td></tr>{open === e.id && <tr><td colSpan={5}><JsonView data={{ raw_log: e.raw_log, normalized: e.normalized }} /></td></tr>}</>)}
      </tbody></table><Pagination meta={data.meta} onPage={setPage} /></>}
    </Card>
  );
}
