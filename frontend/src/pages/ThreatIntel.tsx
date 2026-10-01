import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, EmptyState, Skeleton } from '../components/ui';

export default function ThreatIntel() {
  const [value, setValue] = useState('203.0.113.45'); const [type, setType] = useState('ipv4');
  const [res, setRes] = useState<{ results: { provider: string; verdict: string; score: number; error?: string; mock: boolean }[] } | null>(null);
  const { data: providers } = useQuery({ queryKey: ['providers'], queryFn: async () => (await api.get('/threat-intel/providers')).data.data });
  const lookup = async () => { const r = await api.post('/threat-intel/lookup', { type, value }); setRes(r.data.data); };
  const { data: results, isLoading } = useQuery({ queryKey: ['ti-results'], queryFn: async () => (await api.get('/threat-intel/results')).data });
  return (
    <div className="grid g2">
      <Card title="Providers" sub="Keys from env — mock mode when unconfigured, never fake-live">
        {(providers ?? []).map((p: { slug: string; name: string; status: string; mock_mode: boolean }) => <div key={p.slug} className="field"><span>{p.name}</span><span>{p.status}{p.mock_mode ? ' · mock' : ' · live'}</span></div>)}
      </Card>
      <Card title="Manual lookup" sub="SSRF-safe: indicator string only, allowlisted provider APIs">
        <div className="filters"><select value={type} onChange={(e) => setType(e.target.value)}><option>ipv4</option><option>domain</option><option>url</option><option>file_hash</option></select><input style={{ flex: 1 }} value={value} onChange={(e) => setValue(e.target.value)} /><button className="btn-primary btn-sm" onClick={lookup}>Lookup</button></div>
        {res && res.results.map((r) => <div key={r.provider} className="field"><span>{r.provider}{r.mock ? ' (mock)' : ''}</span><span>{r.verdict} · {r.score}{r.error ? ` · ${r.error}` : ''}</span></div>)}
      </Card>
      <Card title="Recent results">{isLoading ? <Skeleton /> : !results?.data?.length ? <EmptyState title="No lookups yet" /> : <table className="tbl"><tbody>{results.data.map((r: { id: number; indicator_value: string; provider: string; verdict: string }) => <tr key={r.id}><td className="kv">{r.indicator_value}</td><td>{r.provider}</td><td>{r.verdict}</td></tr>)}</tbody></table>}</Card>
    </div>
  );
}
