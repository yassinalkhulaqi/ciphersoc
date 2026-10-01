import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';
import { getEcho } from '../ws/echo';
import { CommandPalette } from './CommandPalette';

const NAV: { to: string; label: string; perm?: string }[] = [
  { to: '/', label: 'Overview', perm: 'dashboard.view' },
  { to: '/alerts', label: 'Alerts', perm: 'alerts.view' },
  { to: '/incidents', label: 'Incidents', perm: 'incidents.view' },
  { to: '/logs', label: 'Logs', perm: 'events.view' },
  { to: '/events', label: 'Events / Logs', perm: 'events.view' },
  { to: '/rules', label: 'Detection Rules', perm: 'rules.view' },
  { to: '/playbooks', label: 'Playbooks', perm: 'playbooks.view' },
  { to: '/mitre', label: 'MITRE ATT&CK', perm: 'dashboard.view' },
  { to: '/iocs', label: 'IOCs', perm: 'iocs.view' },
  { to: '/threat-intel', label: 'Threat Intel', perm: 'threatintel.view' },
  { to: '/assets', label: 'Assets', perm: 'agents.view' },
  { to: '/network', label: 'Network', perm: 'agents.view' },
  { to: '/hosts', label: 'Hosts / Agents', perm: 'agents.view' },
  { to: '/users', label: 'Users', perm: 'users.view' },
  { to: '/audit', label: 'Audit Logs', perm: 'audit.view' },
  { to: '/reports', label: 'Reports', perm: 'reports.view' },
  { to: '/api', label: 'API', perm: 'api.manage' },
  { to: '/settings', label: 'Settings', perm: 'settings.manage' },
];

export default function Layout() {
  const { user, logout, can } = useAuth();
  const { sidebarOpen, toggleSidebar, toasts, toast, dismiss } = useUi();
  const [q, setQ] = useState('');
  const [sev, setSev] = useState('');
  const [bell, setBell] = useState(false);
  const [theme, setTheme] = useState<'dark' | 'light'>('dark');
  const nav = useNavigate();
  const qc = useQueryClient();
  const { data: notifs } = useQuery({ queryKey: ['notifications'], queryFn: async () => (await api.get('/notifications')).data.data, refetchInterval: 30000 });
  const { data: alertCounts } = useQuery({ queryKey: ['nav-counts'], queryFn: async () => (await api.get('/dashboard/overview', { params: { range: '24h' } })).data.data.totals, refetchInterval: 30000 });
  const { data: agents } = useQuery({ queryKey: ['agents-online'], queryFn: async () => (await api.get('/agents')).data.data, refetchInterval: 60000 });

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
  }, [theme]);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const ch = echo.private('soc.alerts');
    ch.listen('.AlertCreated', (e: { title: string; severity: string }) => {
      toast('alert', `New ${e.severity} alert: ${e.title}`);
      try { new AudioContext().close(); } catch { /* sound optional, no asset */ }
      void qc.invalidateQueries({ queryKey: ['alerts'] });
      void qc.invalidateQueries({ queryKey: ['overview'] });
      void qc.invalidateQueries({ queryKey: ['nav-counts'] });
    });
    ch.listen('.AlertUpdated', () => { void qc.invalidateQueries({ queryKey: ['alerts'] }); });
    const onErr = () => {};
    try { echo.connector.pusher.connection.bind('error', onErr); } catch { /* offline ok */ }
    return () => { try { echo.leaveChannel('private-soc.alerts'); } catch { /* noop */ } };
  }, [qc, toast]);

  const unread = (notifs ?? []).filter((n: { read_at?: string }) => !n.read_at).length;

  return (
    <div className="shell">
      <aside className={sidebarOpen ? 'side' : 'side collapsed'}>
        <div className="brand" onClick={() => nav('/')}><span className="brand-mark">◈</span><span className="brand-name">cipherSOC</span><span className="soc-dot" title="SOC live" /></div>
        <nav>{NAV.filter((n) => !n.perm || can(n.perm)).map((n) => {
          const badge = n.to === '/alerts' && alertCounts?.alerts ? alertCounts.alerts : n.to === '/incidents' && alertCounts?.open_incidents ? alertCounts.open_incidents : null;
          return <NavLink key={n.to} to={n.to} end={n.to === '/'} className={({ isActive }) => (isActive ? 'nav-a active' : 'nav-a')}><span>{n.label}</span>{badge ? <span className="nav-badge">{badge}</span> : null}</NavLink>;
        })}</nav>
        <div className="side-foot"><span className="muted">v1.0.0 · blue team</span><div className="muted">Online: {(agents ?? []).filter((a: { status: string }) => a.status === 'online').length ?? '—'}</div></div>
      </aside>
      <div className="main">
        <header className="top">
          <button className="btn-ghost" onClick={toggleSidebar}>☰</button>
          <form className="gsearch" onSubmit={(e) => { e.preventDefault(); nav(`/events?search=${encodeURIComponent(q)}${sev ? ` severity:${sev}` : ''}`); }}><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Global search — Ctrl+K — try severity:critical, mitre:T1110, ioc:8.8.8.8…" /></form>
          <select value={sev} onChange={(e) => setSev(e.target.value)} title="Severity filter"><option value="">All sev</option><option>critical</option><option>high</option><option>medium</option><option>low</option></select>
          <button className="btn-ghost" onClick={() => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))} title="Theme toggle">{theme === 'dark' ? '🌙' : '☀️'}</button>
          <div className="top-r">
            <button className="btn-ghost" onClick={() => setBell((b) => !b)} title="Live alerts">🔔{unread > 0 && <b className="dot">{unread}</b>}</button>
            <span className="muted">{user?.name} · {(user?.roles ?? []).join(',')}</span>
            <button className="btn-ghost" onClick={() => nav('/profile')}>Profile</button>
            <button className="btn-ghost" onClick={() => { void logout().then(() => nav('/login')); }}>Logout</button>
          </div>
        </header>
        {bell && <div className="card" style={{ margin: '8px 16px 0' }}><div className="card-b">{(notifs ?? []).slice(0, 5).map((n: { id: number; title?: string; message?: string }) => <div key={n.id} className="field"><span>🔔</span><span>{n.title ?? n.message ?? 'Notification'}</span></div>) || <span className="muted">No notifications</span>}</div></div>}
        <main className="content"><Outlet /></main>
        <nav className="mobile-nav"><NavLink to="/">Home</NavLink><NavLink to="/alerts">Alerts</NavLink><NavLink to="/logs">Logs</NavLink><NavLink to="/incidents">Cases</NavLink><NavLink to="/settings">More</NavLink></nav>
      </div>
      <CommandPalette />
      <div className="toasts">{toasts.map((t) => <div key={t.id} className={`toast ${t.kind}`} onClick={() => dismiss(t.id)}>{t.text}</div>)}</div>
    </div>
  );
}
