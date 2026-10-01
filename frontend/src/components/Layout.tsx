import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';
import { getEcho } from '../ws/echo';

const NAV: { to: string; label: string; perm?: string }[] = [
  { to: '/', label: 'Overview', perm: 'dashboard.view' },
  { to: '/alerts', label: 'Alerts', perm: 'alerts.view' },
  { to: '/incidents', label: 'Incidents', perm: 'incidents.view' },
  { to: '/events', label: 'Events / Logs', perm: 'events.view' },
  { to: '/rules', label: 'Detection Rules', perm: 'rules.view' },
  { to: '/playbooks', label: 'Playbooks', perm: 'playbooks.view' },
  { to: '/mitre', label: 'MITRE ATT&CK', perm: 'dashboard.view' },
  { to: '/iocs', label: 'IOCs', perm: 'iocs.view' },
  { to: '/threat-intel', label: 'Threat Intel', perm: 'threatintel.view' },
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
  const nav = useNavigate();
  const qc = useQueryClient();
  const { data: notifs } = useQuery({ queryKey: ['notifications'], queryFn: async () => (await api.get('/notifications')).data.data, refetchInterval: 30000 });

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const ch = echo.private('soc.alerts');
    ch.listen('.AlertCreated', (e: { title: string; severity: string }) => {
      toast('alert', `New ${e.severity} alert: ${e.title}`);
      void qc.invalidateQueries({ queryKey: ['alerts'] });
      void qc.invalidateQueries({ queryKey: ['overview'] });
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
        <div className="brand" onClick={() => nav('/')}><span className="brand-mark">◈</span><span className="brand-name">cipherSOC</span></div>
        <nav>{NAV.filter((n) => !n.perm || can(n.perm)).map((n) => <NavLink key={n.to} to={n.to} end={n.to === '/'} className={({ isActive }) => (isActive ? 'nav-a active' : 'nav-a')}>{n.label}</NavLink>)}</nav>
        <div className="side-foot"><span className="muted">v1.0.0 · blue team</span></div>
      </aside>
      <div className="main">
        <header className="top">
          <button className="btn-ghost" onClick={toggleSidebar}>☰</button>
          <form className="gsearch" onSubmit={(e) => { e.preventDefault(); nav(`/events?search=${encodeURIComponent(q)}`); }}><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Global search — try severity:critical, mitre:T1110, ioc:8.8.8.8…" /></form>
          <div className="top-r">
            <button className="btn-ghost" onClick={() => nav('/notifications')} title="Notifications">🔔{unread > 0 && <b className="dot">{unread}</b>}</button>
            <span className="muted">{user?.name} · {(user?.roles ?? []).join(',')}</span>
            <button className="btn-ghost" onClick={() => nav('/profile')}>Profile</button>
            <button className="btn-ghost" onClick={() => { void logout().then(() => nav('/login')); }}>Logout</button>
          </div>
        </header>
        <main className="content"><Outlet /></main>
      </div>
      <div className="toasts">{toasts.map((t) => <div key={t.id} className={`toast ${t.kind}`} onClick={() => dismiss(t.id)}>{t.text}</div>)}</div>
    </div>
  );
}
