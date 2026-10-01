import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { AttackMap } from '../components/soc';
import { Card, Skeleton } from '../components/ui';

export default function Network() {
  const { data, isLoading } = useQuery({ queryKey: ['topology'], queryFn: async () => (await api.get('/network/topology')).data.data });
  if (isLoading) return <Skeleton rows={6} />;
  return (
    <div className="grid g2">
      <Card title="Topology" sub="Assets + last-24h alert edges (source IP → host)">
        {(data?.nodes ?? []).slice(0, 20).map((n: { id: number; hostname: string; ip_address?: string; criticality: string }) => (
          <div key={n.id} className="field"><span className="kv">{n.hostname}</span><span className="muted">{n.ip_address} · {n.criticality}</span></div>
        ))}
        <p className="muted">Edges: {(data?.edges ?? []).length} recent correlations.</p>
      </Card>
      <Card title="Geo attack map" sub="Pulsing sources, hover for IP">
        <AttackMap points={data?.geo ?? []} />
      </Card>
    </div>
  );
}
