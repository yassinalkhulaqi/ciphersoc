import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api/client';
import { RelatedAlerts } from '../components/Correlation';
import { Card, Field, JsonView, RiskFactors, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function AlertDetail() {
  const { id } = useParams();
  const qc = useQueryClient();
  const toast = useUi((s) => s.toast);
  const { can } = useAuth();
  const [tab, setTab] = useState('overview');
  const [comment, setComment] = useState('');
  const { data, isLoading } = useQuery({ queryKey: ['alert', id], queryFn: async () => (await api.get(`/alerts/${id}`)).data.data });
  const upd = useMutation({ mutationFn: async (body: unknown) => (await api.patch(`/alerts/${id}`, body)).data, onSuccess: () => { toast('ok', 'Alert updated'); void qc.invalidateQueries({ queryKey: ['alert', id] }); void qc.invalidateQueries({ queryKey: ['alerts'] }); } });
  const addComment = async () => { if (!comment.trim()) return; await api.post(`/alerts/${id}/comments`, { body: comment }); setComment(''); toast('ok', 'Note added'); void qc.invalidateQueries({ queryKey: ['alert', id] }); };
  if (isLoading) return <Skeleton rows={10} />;
  if (!data) return <div className="muted">Alert not found.</div>;
  const a = data.alert;
  const ctx = a.context ?? {};
  return (
    <div>
      <p className="muted"><Link to="/alerts">Alerts</Link> / #{a.id} · <SeverityBadge value={a.severity} /> <StatusBadge value={a.status} /> · risk {a.risk_score}</p>
      <h2 style={{ margin: '4px 0 12px' }}>{a.title}</h2>
      {can('alerts.update') && <div className="filters">
        {['acknowledged', 'investigating', 'escalated', 'resolved', 'closed', 'false_positive'].map((s) => <button key={s} className="btn-ghost btn-sm" onClick={() => upd.mutate({ status: s })}>{s}</button>)}
        <button className="btn-ghost btn-sm" onClick={async () => { const inc = await api.post('/incidents', { title: `Investigation: ${a.title}`, severity: a.severity, alert_ids: [a.id] }); toast('ok', `Incident ${inc.data.data.incident_id} created`); }}>Create incident</button>
      </div>}
      <div className="tabs">{['overview', 'detection', 'events', 'related', 'intel', 'notes', 'timeline', 'raw'].map((t) => <button key={t} className={tab === t ? 'tab active' : 'tab'} onClick={() => setTab(t)}>{t}</button>)}</div>
      {tab === 'overview' && <div className="grid g2">
        <Card title="Overview"><Field label="Description">{a.description}</Field><Field label="Source">{a.source}</Field><Field label="Rule">{a.rule?.name ?? '—'}</Field><Field label="Occurrences">{a.occurrence_count}</Field><Field label="First seen">{a.first_seen_at}</Field><Field label="Last seen">{a.last_seen_at}</Field><Field label="MITRE">{a.mitre ? `${a.mitre.technique ?? ''} ${a.mitre.tactic ?? ''}` : '—'}</Field></Card>
        <Card title="Risk score — transparent factors"><RiskFactors score={a.risk_score} factors={a.risk_factors} /></Card>
        <Card title="Source"><Field label="Source IP">{ctx.source_ip}</Field><Field label="User">{ctx.username}</Field><Field label="Hostname">{a.host?.hostname ?? ctx.hostname}</Field></Card>
        <Card title="Process"><Field label="Command line"><span className="kv">{ctx.command_line ?? a.events?.[0]?.command_line ?? '—'}</span></Field></Card>
      </div>}
      {tab === 'detection' && <Card title="Detection">{a.rule ? <><Field label="Rule">{a.rule.name} ({a.rule.rule_id})</Field><Field label="Type">{a.rule.rule_type} · threshold {a.rule.threshold}/{a.rule.time_window_minutes}m</Field><JsonView data={a.rule.conditions} /></> : <span className="muted">Manual alert — no rule.</span>}</Card>}
      {tab === 'events' && <Card title={`Related events (${a.events?.length ?? 0})`}>{(a.events ?? []).map((e: { id: number; event_type: string; message: string }) => <div key={e.id} className="field"><span className="kv">{e.event_type}</span><span>{e.message}</span></div>)}</Card>}
      {tab === 'related' && <Card title="Graph correlation — shared IP/host/user/MITRE (7d)"><RelatedAlerts id={String(a.id)} /></Card>}
      {tab === 'intel' && <Card title="Threat intelligence"><Field label="Source IP">{ctx.source_ip ?? '—'}</Field><Field label="Domain">{ctx.domain ?? '—'}</Field><Field label="Hash">{ctx.hash ?? '—'}</Field><p className="muted">Automatic enrichment runs async via queued jobs; verdicts land on linked IOCs (see IOCs page).</p></Card>}
      {tab === 'notes' && <Card title="Investigation notes"><div className="timeline">{(a.comments ?? []).map((c: { id: number; body: string; user?: { name: string }; created_at: string }) => <div key={c.id}><span className="tl-dot" /><b>{c.user?.name}</b> <span className="muted">{c.created_at}</span><div>{c.body}</div></div>)}</div><div className="filters" style={{ marginTop: 10 }}><input style={{ flex: 1 }} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Add analyst note…" /><button className="btn-primary" onClick={addComment}>Add</button></div></Card>}
      {tab === 'timeline' && <Card title="Status timeline"><div className="timeline">{(data.status_history ?? []).map((h: { id: number; from_status: string; to_status: string; created_at: string }) => <div key={h.id}><span className="tl-dot" />{h.from_status} → <b>{h.to_status}</b> <span className="muted">{h.created_at}</span></div>)}</div></Card>}
      {tab === 'raw' && <Card title="Raw context + audit"><JsonView data={{ context: a.context, audit: data.audit_history }} /></Card>}
    </div>
  );
}
