import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, EmptyState, JsonView, Skeleton } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

type Pb = { id: number; name: string; description?: string; enabled: boolean; trigger?: unknown; actions: { type: string }[] };

export default function Playbooks() {
  const qc = useQueryClient(); const toast = useUi((s) => s.toast); const { can } = useAuth();
  const [name, setName] = useState('Auto-acknowledge critical');
  const [alertIds, setAlertIds] = useState('1,2,3');
  const [runRes, setRunRes] = useState<unknown>(null);
  const { data, isLoading } = useQuery({ queryKey: ['playbooks'], queryFn: async () => (await api.get('/playbooks')).data.data as Pb[] });
  const create = useMutation({
    mutationFn: async () => (await api.post('/playbooks', {
      name, trigger: { severity: ['critical'], min_risk: 70 },
      actions: [{ type: 'status', status: 'escalated' }, { type: 'comment', body: 'Auto-triaged by playbook' }, { type: 'create_incident', title: 'Auto-incident' }],
    })).data,
    onSuccess: () => { toast('ok', 'Playbook created'); void qc.invalidateQueries({ queryKey: ['playbooks'] }); },
  });
  const run = async (id: number, dry: boolean) => {
    const ids = alertIds.split(',').map((x) => parseInt(x.trim(), 10)).filter((n) => Number.isFinite(n));
    const r = await api.post(`/playbooks/${id}/run`, { alert_ids: ids, dry_run: dry });
    setRunRes(r.data.data); toast('ok', dry ? 'Dry-run complete' : `Applied to ${r.data.data.matched?.length ?? 0} alerts`);
  };
  return (
    <div className="grid g2">
      <Card title="SOAR Playbooks" sub="Trigger (severity/min-risk) → ordered actions: assign, status, comment, create_incident">
        {can('playbooks.manage') && <div className="filters"><input style={{ flex: 1 }} value={name} onChange={(e) => setName(e.target.value)} placeholder="Playbook name" /><button className="btn-primary btn-sm" onClick={() => create.mutate()}>Create starter</button></div>}
        {isLoading ? <Skeleton /> : !data || data.length === 0 ? <EmptyState title="No playbooks" hint="Seeded defaults appear after migrate --seed." /> : <table className="tbl"><thead><tr><th>Name</th><th>Actions</th><th>On</th><th>Run</th></tr></thead><tbody>
          {data.map((p) => <tr key={p.id}><td>{p.name}<div className="muted">{p.description}</div></td><td className="kv">{p.actions.map((a) => a.type).join(', ')}</td><td>{p.enabled ? '✅' : '⏸'}</td><td><button className="btn-ghost btn-sm" onClick={() => void run(p.id, true)}>Dry-run</button> <button className="btn-ghost btn-sm" onClick={() => void run(p.id, false)}>Run</button></td></tr>)}
        </tbody></table>}
      </Card>
      <Card title="Runner" sub="Comma-separated alert IDs, transparent matched vs skipped">
        <div className="filters"><input style={{ flex: 1 }} value={alertIds} onChange={(e) => setAlertIds(e.target.value)} placeholder="1,2,3" /></div>
        {runRes ? <JsonView data={runRes} /> : <p className="muted">Pick a playbook → Dry-run to preview without side effects.</p>}
      </Card>
    </div>
  );
}
