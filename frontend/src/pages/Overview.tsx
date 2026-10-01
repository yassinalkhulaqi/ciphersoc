import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { api } from '../api/client';
import { Card, EmptyState, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { AttackMap, CountUp, Donut, Spark, Stagger } from '../components/soc';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Overview() {
  const [range, setRange] = useState('24h');
  const { data, isLoading, error } = useQuery({ queryKey: ['overview', range], queryFn: async () => (await api.get('/dashboard/overview', { params: { range } })).data.data });
  const { data: kpis } = useQuery({ queryKey: ['kpis', range], queryFn: async () => (await api.get('/dashboard/kpis', { params: { range } })).data.data });
  const { data: topo } = useQuery({ queryKey: ['topo-mini'], queryFn: async () => (await api.get('/network/topology')).data.data });
  const { data: ti } = useQuery({ queryKey: ['ti-preview'], queryFn: async () => (await api.get('/threat-intel/results', { params: { per_page: 5 } })).data.data });
  if (isLoading) return <Skeleton rows={8} />;
  if (error || !data) return <EmptyState title="Dashboard unavailable" hint="Check backend connectivity." />;
  const sevParts = Object.entries(data.by_severity ?? {}).map(([label, value]) => ({ label, value: value as number, color: label === 'critical' ? '#FF2D55' : label === 'high' ? '#FF9F0A' : label === 'medium' ? '#FFD60A' : label === 'low' ? '#30D158' : '#00F0FF' }));
  return (
    <div>
      <div className="filters">
        {['15m', '1h', '24h', '7d'].map((r) => <button key={r} className={range === r ? 'btn-primary' : 'btn-ghost'} onClick={() => setRange(r)}>{r}</button>)}
      </div>
      <Stagger>
        {(kpis ?? []).map((k: { key: string; label: string; value: number; spark: number[] }) => (
          <Card key={k.key}><div className="stat"><div className="n"><CountUp value={Number(k.value) || 0} /></div><div className="l">{k.label}</div><Spark data={k.spark ?? []} /></div></Card>
        ))}
      </Stagger>
      <div className="grid g4" style={{ marginTop: 4, display: 'none' }} />
      <div className="grid g2" style={{ marginTop: 14 }}>
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
        <Card title="Alerts — live" sub="Animated draw, tick every refetch"><ResponsiveContainer width="100%" height={220}><AreaChart data={data.alerts_over_time ?? []}><CartesianGrid stroke="rgba(0,255,255,.08)" /><XAxis dataKey="h" tick={{ fill: '#8aa0b8', fontSize: 11 }} /><YAxis tick={{ fill: '#8aa0b8' }} /><Tooltip contentStyle={{ background: '#0A1222' }} /><Area dataKey="c" fill="#00F0FF33" stroke="#00F0FF" /></AreaChart></ResponsiveContainer></Card>
        </motion.div>
        <Card title="Severity distribution"><Donut parts={sevParts.length ? sevParts : [{ label: 'none', value: 1, color: '#12233A' }]} /></Card>
      </div>
      <div className="grid g2" style={{ marginTop: 14 }}>
        <Card title="World attack map" sub="Offline grid, pulsing sources"><AttackMap points={topo?.geo ?? []} /></Card>
        <Card title="Live log ticker" sub="Latest events"><div className="log-stream" style={{ maxHeight: 220 }}>{(data.recent_alerts ?? []).slice(0, 8).map((a: { id: number; title: string; severity: string }) => <div key={a.id} className="log-row"><SeverityBadge value={a.severity} /> {a.title}</div>)}</div></Card>
      </div>
      <div className="grid g2" style={{ marginTop: 14 }}>
        <Card title="Recent alerts" right={<Link to="/alerts">View all</Link>}>
          {(data.recent_alerts ?? []).length === 0 ? <EmptyState title="No alerts" /> : <table className="tbl"><tbody>{data.recent_alerts.map((a: { id: number; title: string; severity: string; status: string }) => <tr key={a.id} onClick={() => (location.href = `/alerts/${a.id}`)}><td>{a.title}</td><td><SeverityBadge value={a.severity} /></td><td><StatusBadge value={a.status} /></td></tr>)}</tbody></table>}
        </Card>
        <Card title="Threat intel preview" right={<Link to="/threat-intel">View all</Link>}>
          {(!ti || ti.length === 0) ? <EmptyState title="No intel yet" /> : ti.map((t: { id: number; indicator_value: string; verdict: string }) => <div key={t.id} className="field"><span className="kv">{t.indicator_value}</span><span>{t.verdict}</span></div>)}
        </Card>
      </div>
    </div>
  );
}
