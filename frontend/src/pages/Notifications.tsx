import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, Skeleton } from '../components/ui';

export default function Notifications() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: async () => (await api.get('/notifications')).data.data });
  return (
    <Card title="Notifications" sub="In-app + WebSocket realtime — email/Slack hooks plug in later" right={<button className="btn-ghost btn-sm" onClick={async () => { await api.post('/notifications/read-all'); void qc.invalidateQueries({ queryKey: ['notifications'] }); }}>Mark all read</button>}>
      {isLoading ? <Skeleton /> : (data ?? []).map((n: { id: number; title: string; body?: string; type: string; read_at?: string; created_at: string }) => <div key={n.id} className="field"><span>[{n.type}] {n.title}<div className="muted">{n.body}</div></span><span>{n.read_at ? <span className="muted">read</span> : <button className="btn-ghost btn-sm" onClick={async () => { await api.post(`/notifications/${n.id}/read`); void qc.invalidateQueries({ queryKey: ['notifications'] }); }}>Mark read</button>}</span></div>)}
    </Card>
  );
}
