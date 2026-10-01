import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, getPage } from '../api/client';
import { Card, EmptyState, JsonView, Pagination, SeverityBadge, Skeleton } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Rules() {
  const qc = useQueryClient(); const toast = useUi((s) => s.toast); const { can } = useAuth();
  const [page, setPage] = useState(1); const [open, setOpen] = useState<number | null>(null);
  const [name, setName] = useState(''); const [testRes, setTestRes] = useState<unknown>(null);
  const [sigma, setSigma] = useState('title: SSH Brute Force\nlevel: high\ndetection:\n  selection:\n    CommandLine: sshd\n  condition: selection\ntags:\n  - attack.t1110');
  const [sigmaRes, setSigmaRes] = useState<unknown>(null);
  const { data, isLoading } = useQuery({ queryKey: ['rules', page], queryFn: () => getPage<{ id: number; rule_id: string; name: string; severity: string; enabled: boolean; event_type: string; threshold: number; mitre_technique_id?: string }>('/rules', { page }) });
  const toggle = useMutation({ mutationFn: async (r: { id: number; enabled: boolean }) => (await api.patch(`/rules/${r.id}`, { enabled: !r.enabled })).data, onSuccess: () => { toast('ok', 'Rule toggled'); void qc.invalidateQueries({ queryKey: ['rules'] }); } });
  const test = async (id: number) => { const r = await api.post(`/rules/${id}/test`); setTestRes(r.data.data); };
  const importSigma = async (dry: boolean) => { const r = await api.post('/rules/import-sigma', { yaml: sigma, dry_run: dry }); setSigmaRes(r.data.data); if (!dry) { toast('ok', 'Sigma imported'); void qc.invalidateQueries({ queryKey: ['rules'] }); } };
  return (
    <div>
    <Card title="Detection Rules" sub="Rule builder: conditions + threshold + window + MITRE + cooldown">
      {can('rules.create') && <div className="filters"><input style={{ flex: 1 }} value={name} onChange={(e) => setName(e.target.value)} placeholder="Quick create: rule name (threshold on authentication_failure)…" /><button className="btn-primary btn-sm" onClick={async () => { await api.post('/rules', { name, severity: 'high', event_type: 'authentication_failure', rule_type: 'threshold', conditions: { logic: 'AND', items: [{ field: 'event_type', op: 'equals', value: 'authentication_failure' }] }, threshold: 5, time_window_minutes: 5 }); setName(''); toast('ok', 'Rule created'); void qc.invalidateQueries({ queryKey: ['rules'] }); }}>Create</button></div>}
      {isLoading ? <Skeleton /> : !data || data.items.length === 0 ? <EmptyState title="No rules" /> : <><table className="tbl"><thead><tr><th>Rule</th><th>Severity</th><th>Event</th><th>Thr</th><th>MITRE</th><th>On</th></tr></thead><tbody>
        {data.items.map((r) => <><tr key={r.id} onClick={() => setOpen(open === r.id ? null : r.id)}><td>{r.name}<div className="muted kv">{r.rule_id}</div></td><td><SeverityBadge value={r.severity} /></td><td className="kv">{r.event_type}</td><td>{r.threshold}</td><td className="kv">{r.mitre_technique_id ?? '—'}</td><td>{r.enabled ? '✅' : '⏸'}</td></tr>
        {open === r.id && <tr><td colSpan={6}><div className="filters"><button className="btn-ghost btn-sm" onClick={() => toggle.mutate(r)}>{r.enabled ? 'Disable' : 'Enable'}</button><button className="btn-ghost btn-sm" onClick={() => void test(r.id)}>Preview / test vs recent events</button></div>{testRes ? <JsonView data={testRes} /> : null}</td></tr>}</>)}
      </tbody></table><Pagination meta={data.meta} onPage={setPage} /></>}
    </Card>
    {can('rules.create') && <Card title="Sigma import" sub="Paste community Sigma YAML → preview → import as native rule"><textarea style={{ width: '100%', minHeight: 90 }} value={sigma} onChange={(e) => setSigma(e.target.value)} /><div className="filters"><button className="btn-ghost btn-sm" onClick={() => void importSigma(true)}>Preview</button><button className="btn-primary btn-sm" onClick={() => void importSigma(false)}>Import</button></div>{sigmaRes ? <JsonView data={sigmaRes} /> : null}</Card>}
    </div>
  );
}
