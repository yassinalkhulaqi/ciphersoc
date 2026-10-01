import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, getPage } from '../api/client';
import { Card, EmptyState, Pagination, Skeleton } from '../components/ui';

export default function Assets() {
  const [page, setPage] = useState(1); const [search, setSearch] = useState('');
  const { data, isLoading } = useQuery({ queryKey: ['assets', page, search], queryFn: () => getPage<{ id: number; hostname: string; ip_address?: string; os?: string; criticality: string; status: string; vulns_count: number }>('/assets', { page, search: search || undefined }) });
  const [open, setOpen] = useState<number | null>(null);
  const { data: vulns } = useQuery({ queryKey: ['vulns', open], queryFn: async () => open ? (await api.get(`/assets/${open}/vulns`)).data.data : [], enabled: open !== null });
  return (
    <Card title="Assets" sub="Servers, endpoints, vulnerabilities — criticality + live status">
      <div className="filters"><input style={{ flex: 1 }} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} placeholder="Search hostname, IP, OS…" /></div>
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No assets" hint="Agents auto-register hosts; or POST /assets." /> : <>
        <table className="tbl"><thead><tr><th>Hostname</th><th>IP</th><th>OS</th><th>Criticality</th><th>Status</th><th>Vulns</th></tr></thead><tbody>
          {data.items.map((a) => <><tr key={a.id} onClick={() => setOpen(open === a.id ? null : a.id)}><td className="kv">{a.hostname}</td><td className="kv">{a.ip_address ?? '—'}</td><td>{a.os ?? '—'}</td><td>{a.criticality}</td><td>{a.status}</td><td>{a.vulns_count}</td></tr>
          {open === a.id && <tr><td colSpan={6}>{!vulns || vulns.length === 0 ? <span className="muted">No open vulnerabilities.</span> : vulns.map((v: { id: number; cve?: string; title: string; severity: string; cvss?: number }) => <div key={v.id} className="field"><span className="kv">{v.cve ?? '—'}</span><span>{v.title} · {v.severity}{v.cvss ? ` · CVSS ${v.cvss}` : ''}</span></div>)}</td></tr>}</>)}
        </tbody></table><Pagination meta={data.meta} onPage={setPage} />
      </>}
    </Card>
  );
}
