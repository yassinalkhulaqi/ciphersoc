import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getPage } from '../api/client';
import { Card, EmptyState, Pagination, SeverityBadge, Skeleton } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Iocs() {
  const qc = useQueryClient(); const toast = useUi((s) => s.toast); const { can } = useAuth();
  const [page, setPage] = useState(1); const [search, setSearch] = useState('');
  const [value, setValue] = useState(''); const [type, setType] = useState('ipv4');
  const { data, isLoading } = useQuery({ queryKey: ['iocs', page, search], queryFn: () => getPage<{ id: number; type: string; value: string; reputation: string; threat_score: number; severity: string; status: string }>('/iocs', { page, search: search || undefined }) });
  const create = useMutation({ mutationFn: async () => (await api.post('/iocs', { type, value })).data, onSuccess: () => { setValue(''); toast('ok', 'IOC created — enrichment queued'); void qc.invalidateQueries({ queryKey: ['iocs'] }); }, onError: (e: { response?: { data?: { message?: string } } }) => toast('alert', e.response?.data?.message ?? 'Create failed') });
  const enrich = async (id: number) => { await api.post(`/iocs/${id}/enrich`); toast('ok', 'Enrichment queued'); };
  return (
    <Card title="IOCs" sub="Normalized + deduplicated — VT / AbuseIPDB / OTX / URLhaus enrichment">
      {can('iocs.create') && <div className="filters"><select value={type} onChange={(e) => setType(e.target.value)}><option>ipv4</option><option>ipv6</option><option>domain</option><option>hostname</option><option>url</option><option>file_hash</option><option>email</option></select><input style={{ flex: 1 }} value={value} onChange={(e) => setValue(e.target.value)} placeholder="8.8.8.8 / evil.example / https://… / sha256…" /><button className="btn-primary btn-sm" onClick={() => create.mutate()}>Add IOC</button></div>}
      <div className="filters"><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search IOC value…" /></div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No IOCs" /> : <><table className="tbl"><thead><tr><th>Value</th><th>Type</th><th>Rep</th><th>Score</th><th>Sev</th><th>Status</th><th></th></tr></thead><tbody>
        {data.items.map((i) => <tr key={i.id}><td className="kv">{i.value.slice(0, 60)}</td><td>{i.type}</td><td>{i.reputation}</td><td>{i.threat_score}</td><td><SeverityBadge value={i.severity} /></td><td>{i.status}</td><td>{can('iocs.update') && <button className="btn-ghost btn-sm" onClick={() => void enrich(i.id)}>Enrich</button>}</td></tr>)}
      </tbody></table><Pagination meta={data.meta} onPage={setPage} /></>}
    </Card>
  );
}
