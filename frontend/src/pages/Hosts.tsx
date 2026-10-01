import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { getPage } from '../api/client';
import { Card, EmptyState, Pagination, Skeleton, StatusBadge } from '../components/ui';

export default function Hosts() {
  const [page, setPage] = useState(1);
  const { data: agents, isLoading } = useQuery({ queryKey: ['agents', page], queryFn: () => getPage<{ id: number; agent_id: string; hostname: string; status: string; agent_version?: string; last_heartbeat_at?: string }>('/agents', { page }) });
  const { data: hosts } = useQuery({ queryKey: ['hosts'], queryFn: () => getPage<{ id: number; hostname: string; os?: string; ip_address?: string; status: string }>('/hosts', {}) });
  return (
    <div className="grid g2">
      <Card title="Agents" sub="Heartbeat-monitored — offline after 5 min silence">
        {isLoading ? <Skeleton /> : !agents || agents.items.length === 0 ? <EmptyState title="No agents" hint="Run the Python agent to enroll." /> : <><table className="tbl"><thead><tr><th>Agent</th><th>Host</th><th>Status</th><th>Version</th></tr></thead><tbody>{agents.items.map((a) => <tr key={a.id}><td className="kv">{a.agent_id}</td><td>{a.hostname}</td><td><StatusBadge value={a.status} /></td><td>{a.agent_version ?? '—'}</td></tr>)}</tbody></table><Pagination meta={agents.meta} onPage={setPage} /></>}
      </Card>
      <Card title="Hosts">{!hosts || hosts.items.length === 0 ? <EmptyState title="No hosts" /> : <table className="tbl"><thead><tr><th>Hostname</th><th>OS</th><th>IP</th><th>Status</th></tr></thead><tbody>{hosts.items.map((h) => <tr key={h.id}><td>{h.hostname}</td><td>{h.os ?? '—'}</td><td className="kv">{h.ip_address ?? '—'}</td><td><StatusBadge value={h.status} /></td></tr>)}</tbody></table>}</Card>
    </div>
  );
}
