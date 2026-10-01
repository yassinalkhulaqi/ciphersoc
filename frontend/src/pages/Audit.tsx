import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getPage } from '../api/client';
import { Card, EmptyState, Pagination, Skeleton } from '../components/ui';

export default function Audit() {
  const [page, setPage] = useState(1); const [action, setAction] = useState('');
  const { data, isLoading } = useQuery({ queryKey: ['audit', page, action], queryFn: () => getPage<{ id: number; action: string; resource_type?: string; resource_id?: string; actor?: { name: string }; created_at: string }>('/audit-logs', { page, action: action || undefined }) });
  return (
    <Card title="Audit Logs" sub="Who did what, when — secrets never logged">
      <div className="filters"><input value={action} onChange={(e) => setAction(e.target.value)} placeholder="Filter action, e.g. alert.update…" /></div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No audit entries" /> : <><table className="tbl"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Resource</th></tr></thead><tbody>{data.items.map((a) => <tr key={a.id}><td className="muted">{new Date(a.created_at).toLocaleString()}</td><td>{a.actor?.name ?? 'system'}</td><td className="kv">{a.action}</td><td className="kv">{a.resource_type ?? ''} {a.resource_id ?? ''}</td></tr>)}</tbody></table><Pagination meta={data.meta} onPage={setPage} /></>}
    </Card>
  );
}
