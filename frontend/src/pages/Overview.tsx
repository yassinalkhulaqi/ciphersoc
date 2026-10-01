import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { Card, EmptyState, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function Overview() {
  const [range, setRange] = useState('24h');
  const { data, isLoading, error } = useQuery({ queryKey: ['overview', range], queryFn: async () => (await api.get('/dashboard/overview', { params: { range } })).data.data });
  if (isLoading) return <Skeleton rows={8} />;
  if (error || !data) return <EmptyState title="Dashboard unavailable" hint="Check backend connectivity." />;
  const t = data.totals;
  const stats: [string, number][] = [['Total alerts', t.alerts], ['Critical', t.critical], ['Open incidents', t.open_incidents], ['Events', t.events], ['Events/min', t.events_per_minute], ['Agents online', t.agents_online], ['Agents offline', t.agents_offline], ['TI lookups', t.threatintel_lookups]];
  return (
    <div>
      <div className="filters">
        {['15m', '1h', '24h', '7d'].map((r) => <button key={r} className={range === r ? 'btn-primary' : 'btn-ghost'} onClick={() => setRange(r)}>{r}</button>)}
      </div>
      <div className="grid g4">{stats.map(([l, v]) => <Card key={l}><div className="stat"><div className="n">{v}</div><div className="l">{l}</div></div></Card>)}</div>
      <div className="grid g2" style={{ marginTop: 14 }}>
        <Card title="Alerts over time" sub="Hourly buckets"><ResponsiveContainer width="100%" height={220}><AreaChart data={data.alerts_over_time ?? []}><CartesianGrid stroke="#1c2942" /><XAxis dataKey="h" tick={{ fill: '#8aa0b8', fontSize: 11 }} /><YAxis tick={{ fill: '#8aa0b8' }} /><Tooltip contentStyle={{ background: '#0e1626' }} /><Area dataKey="c" fill="#22d3ee" stroke="#22d3ee" /></AreaChart></ResponsiveContainer></Card>
        <Card title="Top source IPs"><ResponsiveContainer width="100%" height={220}><BarChart data={data.top_source_ips ?? []} layout="vertical"><CartesianGrid stroke="#1c2942" /><XAxis type="number" tick={{ fill: '#8aa0b8' }} /><YAxis type="category" dataKey="source_ip" width={120} tick={{ fill: '#8aa0b8', fontSize: 11 }} /><Tooltip contentStyle={{ background: '#0e1626' }} /><Bar dataKey="c" fill="#f97316" /></BarChart></ResponsiveContainer></Card>
      </div>
      <div className="grid g2" style={{ marginTop: 14 }}>
        <Card title="Recent alerts" right={<Link to="/alerts">View all</Link>}>
          {(data.recent_alerts ?? []).length === 0 ? <EmptyState title="No alerts" /> : <table className="tbl"><tbody>{data.recent_alerts.map((a: { id: number; title: string; severity: string; status: string }) => <tr key={a.id} onClick={() => (location.href = `/alerts/${a.id}`)}><td>{a.title}</td><td><SeverityBadge value={a.severity} /></td><td><StatusBadge value={a.status} /></td></tr>)}</tbody></table>}
        </Card>
        <Card title="Recent incidents" right={<Link to="/incidents">View all</Link>}>
          {(data.recent_incidents ?? []).length === 0 ? <EmptyState title="No incidents" /> : <table className="tbl"><tbody>{data.recent_incidents.map((i: { id: number; title: string; severity: string; status: string }) => <tr key={i.id} onClick={() => (location.href = `/incidents/${i.id}`)}><td>{i.title}</td><td><SeverityBadge value={i.severity} /></td><td><StatusBadge value={i.status} /></td></tr>)}</tbody></table>}
        </Card>
      </div>
    </div>
  );
}
