import { useCallback, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api, getPage } from '../api/client';
import { useDebouncedValue } from '../hooks/useDebouncedValue';
import { useKeyboardTriage } from '../hooks/useKeyboardTriage';
import { Card, EmptyState, Pagination, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { useUi } from '../store/ui';

export default function Alerts() {
  const nav = useNavigate();
  const toast = useUi((s) => s.toast);
  const [page, setPage] = useState(1);
  const [severity, setSeverity] = useState('');
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 350);
  const [sel, setSel] = useState<number[]>([]);
  const [cursor, setCursor] = useState<number | null>(null);
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['alerts', page, severity, status, debouncedSearch],
    queryFn: () => getPage<{ id: number; title: string; severity: string; status: string; risk_score: number; created_at: string }>('/alerts', { page, severity: severity || undefined, status: status || undefined, search: debouncedSearch || undefined }),
  });
  const bulk = useCallback(async (action: string) => {
    const ids = sel.length > 0 ? sel : cursor !== null && data?.items[cursor] ? [data.items[cursor].id] : [];
    if (ids.length === 0) return;
    await api.post('/alerts/bulk', { ids, action });
    toast('ok', `Bulk ${action} applied to ${ids.length} alerts`);
    setSel([]); void refetch();
  }, [sel, cursor, data, toast, refetch]);
  const onAction = useCallback((action: string) => { void bulk(action); }, [bulk]);
  useKeyboardTriage(data?.items.length ?? 0, cursor, setCursor, onAction);
  const exportCsv = () => {
    const rows = (data?.items ?? []).map((a) => [a.id, `"${a.title.replace(/"/g, '""')}"`, a.severity, a.status, a.risk_score, a.created_at].join(','));
    const blob = new Blob([`id,title,severity,status,risk,created\n${rows.join('\n')}`], { type: 'text/csv' });
    const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'alerts.csv'; link.click(); URL.revokeObjectURL(url);
  };
  return (
    <Card title="Alerts" sub="DETECT → TRIAGE — j/k navigate, a/e/r triage, Esc clear. Grammar: severity: status: rule: mitre: ioc: + free text." right={<div><button className="btn-ghost btn-sm" onClick={() => void bulk('acknowledge')}>Acknowledge</button> <button className="btn-ghost btn-sm" onClick={() => void bulk('resolve')}>Resolve</button> <button className="btn-ghost btn-sm" onClick={() => void bulk('close')}>Close</button> <button className="btn-ghost btn-sm" onClick={exportCsv}>CSV</button></div>}>
      <div className="filters">
        <input placeholder="severity:high status:new mitre:T1110 brute…" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        <select value={severity} onChange={(e) => setSeverity(e.target.value)}><option value="">All severities</option><option>critical</option><option>high</option><option>medium</option><option>low</option><option>info</option></select>
        <select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">All statuses</option><option>new</option><option>acknowledged</option><option>investigating</option><option>escalated</option><option>resolved</option><option>closed</option><option>false_positive</option></select>
      </div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No alerts" hint="Alerts appear here when detection rules fire." /> : (
        <><table className="tbl"><thead><tr><th></th><th>Title</th><th>Severity</th><th>Status</th><th>Risk</th><th>Created</th></tr></thead><tbody>
          {data.items.map((a, i) => <tr key={a.id} style={cursor === i ? { outline: '1px solid #22d3ee' } : undefined}><td onClick={(e) => e.stopPropagation()}><input type="checkbox" checked={sel.includes(a.id)} onChange={() => setSel((s) => (s.includes(a.id) ? s.filter((x) => x !== a.id) : [...s, a.id]))} /></td><td onClick={() => nav(`/alerts/${a.id}`)}>{a.title}</td><td><SeverityBadge value={a.severity} /></td><td><StatusBadge value={a.status} /></td><td>{a.risk_score}</td><td className="muted">{new Date(a.created_at).toLocaleString()}</td></tr>)}
        </tbody></table><Pagination meta={data.meta} onPage={(p) => { setPage(p); setCursor(null); }} /></>
      )}
    </Card>
  );
}
