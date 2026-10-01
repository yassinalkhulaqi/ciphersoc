import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api/client';
import { Card, Field, SeverityBadge, Skeleton, StatusBadge } from '../components/ui';
import { useUi } from '../store/ui';

export default function IncidentDetail() {
  const { id } = useParams(); const qc = useQueryClient(); const toast = useUi((s) => s.toast);
  const [note, setNote] = useState(''); const [alertId, setAlertId] = useState('');
  const { data, isLoading } = useQuery({ queryKey: ['incident', id], queryFn: async () => (await api.get(`/incidents/${id}`)).data.data });
  const upd = useMutation({ mutationFn: async (b: unknown) => (await api.patch(`/incidents/${id}`, b)).data, onSuccess: () => { toast('ok', 'Incident updated'); void qc.invalidateQueries({ queryKey: ['incident', id] }); } });
  if (isLoading) return <Skeleton rows={10} />;
  if (!data) return <div className="muted">Not found.</div>;
  const inc = data.incident;
  return (
    <div>
      <p className="muted"><Link to="/incidents">Incidents</Link> / {inc.incident_id} · <SeverityBadge value={inc.severity} /> <StatusBadge value={inc.status} /></p>
      <h2 style={{ margin: '4px 0 12px' }}>{inc.title}</h2>
      <div className="filters">{['investigating', 'containment', 'eradication', 'recovery', 'resolved', 'closed'].map((s) => <button key={s} className="btn-ghost btn-sm" onClick={() => upd.mutate({ status: s })}>{s}</button>)}</div>
      <div className="grid g2">
        <Card title="Overview"><Field label="Description">{inc.description}</Field><Field label="Priority">{inc.priority}</Field><Field label="Assignee">{inc.assignee?.name ?? 'Unassigned'}</Field><Field label="Team">{inc.team}</Field><Field label="MITRE">{(inc.mitre_techniques ?? []).join(', ') || '—'}</Field></Card>
        <Card title="Attach alert"><div className="filters"><input value={alertId} onChange={(e) => setAlertId(e.target.value)} placeholder="Alert ID" /><button className="btn-ghost btn-sm" onClick={async () => { await api.post(`/incidents/${id}/alerts`, { alert_ids: [Number(alertId)] }); toast('ok', 'Alert attached'); void qc.invalidateQueries({ queryKey: ['incident', id] }); }}>Attach</button></div>
          {(inc.alerts ?? []).map((a: { id: number; title: string }) => <div key={a.id} className="field"><Link to={`/alerts/${a.id}`}>{a.title}</Link><span /></div>)}</Card>
        <Card title="IOCs">{(inc.iocs ?? []).map((x: { id: number; value: string; type: string }) => <div key={x.id} className="field"><span className="kv">{x.value}</span><span className="muted">{x.type}</span></div>)}</Card>
        <Card title="Evidence / notes"><div className="timeline">{(inc.timeline ?? []).map((t: { id: number; title: string; detail?: string; created_at: string }) => <div key={t.id}><span className="tl-dot" /><b>{t.title}</b> <span className="muted">{t.created_at}</span><div className="muted">{t.detail}</div></div>)}</div>
          <div className="filters" style={{ marginTop: 10 }}><input style={{ flex: 1 }} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Add timeline note…" /><button className="btn-primary btn-sm" onClick={async () => { await api.post(`/incidents/${id}/timeline`, { title: note }); setNote(''); void qc.invalidateQueries({ queryKey: ['incident', id] }); }}>Add</button></div></Card>
      </div>
    </div>
  );
}
