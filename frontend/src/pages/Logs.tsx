import { useEffect, useRef, useState } from 'react';
import { Card } from '../components/ui';

type LogRow = { id: number; event_type: string; severity: string; hostname?: string; message: string; event_timestamp: string };

export default function Logs() {
  const [rows, setRows] = useState<LogRow[]>([]);
  const [paused, setPaused] = useState(false);
  const [search, setSearch] = useState('');
  const [severity, setSeverity] = useState('');
  const boxRef = useRef<HTMLDivElement>(null);
  const pausedRef = useRef(false);
  pausedRef.current = paused;

  useEffect(() => {
    const es = new EventSource(`/api/v1/logs/stream?search=${encodeURIComponent(search)}&severity=${encodeURIComponent(severity)}`);
    es.onmessage = (ev) => {
      if (pausedRef.current) return;
      try {
        const row = JSON.parse(ev.data) as LogRow;
        setRows((prev) => [...prev.slice(-199), row]);
        requestAnimationFrame(() => boxRef.current?.scrollTo({ top: 999999 }));
      } catch { /* heartbeat */ }
    };
    return () => es.close();
  }, [search, severity]);

  return (
    <Card title="Live log stream" sub="SSE 1 batch / 2s — hover to pause, new rows highlight">
      <div className="filters">
        <input style={{ flex: 1 }} value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search stream…" />
        <select value={severity} onChange={(e) => setSeverity(e.target.value)}><option value="">All</option><option>critical</option><option>high</option><option>medium</option><option>low</option><option>info</option></select>
        <button className="btn-ghost btn-sm" onClick={() => setPaused((p) => !p)}>{paused ? 'Resume' : 'Pause'}</button>
        <button className="btn-ghost btn-sm" onClick={() => setRows([])}>Clear</button>
      </div>
      <div ref={boxRef} className="log-stream" onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}>
        {rows.length === 0 ? <span className="muted">Waiting for logs… keep agent streaming.</span> : rows.map((r) => (
          <div key={r.id} className="log-row new"><span className="muted">{new Date(r.event_timestamp).toLocaleTimeString()}</span> <span className="kv">{r.event_type}</span> <b>{r.severity}</b> <span className="kv">{r.hostname}</span> {r.message}</div>
        ))}
      </div>
    </Card>
  );
}
