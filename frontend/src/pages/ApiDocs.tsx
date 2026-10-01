import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, JsonView, Skeleton } from '../components/ui';

export default function ApiDocs() {
  const { data, isLoading } = useQuery({ queryKey: ['openapi'], queryFn: async () => (await api.get('/openapi.json', { baseURL: (import.meta.env.VITE_API_URL ?? '').replace(/\/v1$/, '') })).data });
  const curl = `curl -X POST ${import.meta.env.VITE_API_URL}/ingest/events \
  -H "Authorization: Bearer <AGENT_TOKEN>" -H "X-Agent-ID: <AGENT_ID>" \
  -H "Content-Type: application/json" \
  -d '{"events":[{"message":"Nov 12 10:11:12 web-01 sshd[1]: Failed password for root from 10.0.0.1 port 22 ssh2"}]}'`;
  return (
    <div className="grid g2">
      <Card title="cipherSOC API" sub="Versioned REST · Bearer auth · consistent envelope"><pre className="json">{curl}</pre><p className="muted">Envelope: {'{success, data, message, meta}'} · Errors: {'{success:false, message, errors}'}</p></Card>
      <Card title="OpenAPI">{isLoading ? <Skeleton /> : <JsonView data={data} />}</Card>
    </div>
  );
}
