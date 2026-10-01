import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';

const ROUTES = [
  { to: '/', label: 'Overview' }, { to: '/alerts', label: 'Alerts' }, { to: '/incidents', label: 'Incidents' },
  { to: '/logs', label: 'Logs' }, { to: '/events', label: 'Events' }, { to: '/threat-intel', label: 'Threat Intel' },
  { to: '/assets', label: 'Assets' }, { to: '/network', label: 'Network' }, { to: '/playbooks', label: 'Playbooks' },
  { to: '/reports', label: 'Reports' }, { to: '/users', label: 'Users' }, { to: '/settings', label: 'Settings' },
];

export function CommandPalette() {
  const nav = useNavigate();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setOpen((o) => !o); }
      if (e.key === 'Escape') setOpen(false);
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, []);
  const items = useMemo(() => ROUTES.filter((r) => r.label.toLowerCase().includes(q.toLowerCase())), [q]);
  if (!open) return null;
  return (
    <div className="cmdk" onClick={() => setOpen(false)}>
      <div className="cmdk-box" onClick={(e) => e.stopPropagation()}>
        <input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Type a command or search… (Esc to close)" />
        {items.map((r) => <div key={r.to} className="cmdk-item" onClick={() => { nav(r.to); setOpen(false); }}><span>{r.label}</span><span className="muted kv">{r.to}</span></div>)}
      </div>
    </div>
  );
}
