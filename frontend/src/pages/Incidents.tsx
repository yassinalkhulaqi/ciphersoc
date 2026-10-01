import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { api, getPage } from '../api/client';
import { Card, EmptyState, Pagination, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { useUi } from '../store/ui';

export default function Incidents() {
  const nav = useNavigate(); const qc = useQueryClient(); const toast = useUi((s) => s.toast);
  const [page, setPage] = useState(1); const [status, setStatus] = useState(''); const [show, setShow] = useState(false);
  const [title, setTitle] = useState(''); const [severity, setSeverity] = useState('high');
  const { data, isLoading } = useQuery({ queryKey: ['incidents', page, status], queryFn: () => getPage<{ id: number; incident_id: string; title: string; severity: string; status: string }>('/incidents', { page, status: status || undefined }) });
  const create = useMutation({ mutationFn: async () => (await api.post('/incidents', { title, severity })).data, onSuccess: () => { setShow(false); setTitle(''); toast('ok', 'Incident created'); void qc.invalidateQueries({ queryKey: ['incidents'] }); } });
  return (
    <Card title="Incidents" sub="CONTAIN → RESOLVE — higher-level investigations" right={<button className="btn-primary btn-sm" onClick={() => setShow(true)}>New incident</button>}>
      {show && <div className="filters"><input style={{ flex: 1 }} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Incident title" /><select value={severity} onChange={(e) => setSeverity(e.target.value)}><option>critical</option><option>high</option><option>medium</option><option>low</option></select><button className="btn-primary btn-sm" onClick={() => create.mutate()}>Create</button></div>}
      <div className="filters"><select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">All statuses</option><option>open</option><option>investigating</option><option>containment</option><option>eradication</option><option>recovery</option><option>resolved</option><option>closed</option></select></div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No incidents" /> : <><table className="tbl"><thead><tr><th>ID</th><th>Title</th><th>Severity</th><th>Status</th></tr></thead><tbody>{data.items.map((i) => <tr key={i.id} onClick={() => nav(`/incidents/${i.id}`)}><td className="kv">{i.incident_id}</td><td>{i.title}</td><td><SeverityBadge value={i.severity} /></td><td><StatusBadge value={i.status} /></td></tr>)}</tbody></table><Pagination meta={data.meta} onPage={setPage} /></>}
    </Card>
  );
}
