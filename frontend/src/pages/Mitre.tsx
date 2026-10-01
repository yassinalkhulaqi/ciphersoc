import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { MitreCoverage } from '../components/Correlation';
import { Card, EmptyState, Skeleton } from '../components/ui';

export default function Mitre() {
  const [tactic, setTactic] = useState(''); const [search, setSearch] = useState('');
  const { data: tactics } = useQuery({ queryKey: ['tactics'], queryFn: async () => (await api.get('/mitre/tactics')).data.data });
  const { data, isLoading } = useQuery({ queryKey: ['techniques', tactic, search], queryFn: async () => (await api.get('/mitre/techniques', { params: { tactic: tactic || undefined, search: search || undefined, per_page: 100 } })).data.data });
  return (
    <div>
      <MitreCoverage />
      <div className="grid g2" style={{ marginTop: 12 }}>
      <Card title="Tactics"><div className="timeline">{(tactics ?? []).map((t: { id: number; tactic_id: string; name: string }) => <div key={t.id}><span className="tl-dot" /><b>{t.name}</b> <span className="muted kv">{t.tactic_id}</span></div>)}</div></Card>
      <Card title="Techniques" sub="Mapped detections + alert counts">
        <div className="filters"><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search T1059, brute force…" /><select value={tactic} onChange={(e) => setTactic(e.target.value)}><option value="">All tactics</option>{(tactics ?? []).map((t: { tactic_id: string; name: string }) => <option key={t.tactic_id} value={t.name.toLowerCase().replace(/ /g, '-')}>{t.name}</option>)}</select></div>
        {isLoading ? <Skeleton /> : !data || data.length === 0 ? <EmptyState title="No techniques" /> : <table className="tbl"><thead><tr><th>ID</th><th>Name</th><th>Alerts</th></tr></thead><tbody>{data.map((t: { technique_id: string; name: string; alerts_count: number; description: string }) => <tr key={t.technique_id} title={t.description}><td className="kv">{t.technique_id}</td><td>{t.name}</td><td>{t.alerts_count}</td></tr>)}</tbody></table>}
      </Card>
      </div>
    </div>
  );
}
