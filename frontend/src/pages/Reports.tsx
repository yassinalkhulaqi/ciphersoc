import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, Skeleton } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Reports() {
  const qc = useQueryClient(); const toast = useUi((s) => s.toast); const { can } = useAuth();
  const [type, setType] = useState('soc_summary'); const [title, setTitle] = useState('Weekly SOC summary');
  const { data, isLoading } = useQuery({
    queryKey: ['reports'],
    queryFn: async () => {
      const body = (await api.get('/reports')).data;
      const page = body.data;
      return (Array.isArray(page) ? page : (page?.data ?? [])) as { id: number; report_id: string; title: string; type: string; status: string }[];
    },
  });
  const gen = useMutation({ mutationFn: async () => (await api.post('/reports', { type, title })).data, onSuccess: () => { toast('ok', 'Report queued — PDF generates async'); void qc.invalidateQueries({ queryKey: ['reports'] }); } });
  const rows = data ?? [];
  const download = async (id: number) => {
    try {
      const res = await api.post(`/reports/${id}/link`);
      window.location.href = res.data.data.url;
    } catch { toast('alert', 'Download link failed — report may not be ready'); }
  };
  return (
    <Card title="Reports" sub="Server-side PDF with cipherSOC branding">
      {can('reports.generate') && <div className="filters"><select value={type} onChange={(e) => setType(e.target.value)}><option>soc_summary</option><option>incident</option><option>alert</option><option>threatintel</option></select><input style={{ flex: 1 }} value={title} onChange={(e) => setTitle(e.target.value)} /><button className="btn-primary btn-sm" onClick={() => gen.mutate()}>Generate PDF</button></div>}
      {isLoading ? <Skeleton /> : <table className="tbl"><thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>{rows.map((r) => <tr key={r.id}><td className="kv">{r.report_id}</td><td>{r.title}</td><td>{r.type}</td><td>{r.status}</td><td>{r.status === 'completed' && <button className="btn-ghost btn-sm" onClick={() => void download(r.id)}>Download</button>}</td></tr>)}</tbody></table>}
    </Card>
  );
}
