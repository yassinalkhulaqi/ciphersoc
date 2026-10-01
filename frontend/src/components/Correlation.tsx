import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, Skeleton } from '../components/ui';

export function RelatedAlerts({ id }: { id: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['related', id], queryFn: async () => (await api.get(`/correlations/alerts/${id}`)).data.data });
  if (isLoading) return <Skeleton rows={3} />;
  if (!data || data.items.length === 0) return <p className="muted">No correlated alerts in 7d window.</p>;
  return (
    <div>
      {data.items.map((r: { id: number; title: string; severity: string; score: number; reasons: string[] }) => (
        <div key={r.id} className="field"><a href={`/alerts/${r.id}`}>#{r.id} {r.title}</a><span className="muted"> score {r.score} · {r.reasons.join(', ')}</span></div>
      ))}
    </div>
  );
}

export function MitreCoverage() {
  const { data, isLoading } = useQuery({ queryKey: ['mitre-coverage'], queryFn: async () => (await api.get('/mitre/coverage')).data.data });
  if (isLoading) return <Skeleton rows={4} />;
  if (!data) return null;
  return (
    <Card title={`MITRE coverage — ${data.coverage_pct}% (${data.techniques_covered}/${data.techniques_total})`} sub="Rules mapped vs techniques with alerts">
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
        {(data.items ?? []).slice(0, 120).map((t: { technique_id: string; covered: boolean; alerts: number }) => (
          <span key={t.technique_id} title={`${t.technique_id} · alerts ${t.alerts}`} style={{ padding: '2px 6px', borderRadius: 4, fontSize: 11, background: t.alerts > 0 ? '#7f1d1d' : t.covered ? '#14532d' : '#1c2942', color: '#e2e8f0' }}>{t.technique_id}</span>
        ))}
      </div>
    </Card>
  );
}
