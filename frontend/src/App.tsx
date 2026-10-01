import { Navigate, Route, Routes } from 'react-router-dom';
import type { ReactElement } from 'react';
import Layout from './components/Layout';
import { useAuth } from './store/auth';
import Login from './pages/Login';
import Overview from './pages/Overview';
import Alerts from './pages/Alerts';
import AlertDetail from './pages/AlertDetail';
import Incidents from './pages/Incidents';
import IncidentDetail from './pages/IncidentDetail';
import Events from './pages/Events';
import Rules from './pages/Rules';
import Mitre from './pages/Mitre';
import Iocs from './pages/Iocs';
import ThreatIntel from './pages/ThreatIntel';
import Hosts from './pages/Hosts';
import Users from './pages/Users';
import Audit from './pages/Audit';
import Reports from './pages/Reports';
import ApiDocs from './pages/ApiDocs';
import Settings from './pages/Settings';
import Profile from './pages/Profile';
import Notifications from './pages/Notifications';

function Guard({ children, perm }: { children: ReactElement; perm?: string }) {
  const { user, loading, can } = useAuth();
  if (loading) return <div className="login-wrap"><div className="muted">Loading cipherSOC…</div></div>;
  if (!user) return <Navigate to="/login" replace />;
  if (perm && !can(perm)) return <Navigate to="/" replace />;
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route element={<Guard><Layout /></Guard>}>
        <Route path="/" element={<Guard perm="dashboard.view"><Overview /></Guard>} />
        <Route path="/alerts" element={<Guard perm="alerts.view"><Alerts /></Guard>} />
        <Route path="/alerts/:id" element={<Guard perm="alerts.view"><AlertDetail /></Guard>} />
        <Route path="/incidents" element={<Guard perm="incidents.view"><Incidents /></Guard>} />
        <Route path="/incidents/:id" element={<Guard perm="incidents.view"><IncidentDetail /></Guard>} />
        <Route path="/events" element={<Guard perm="events.view"><Events /></Guard>} />
        <Route path="/rules" element={<Guard perm="rules.view"><Rules /></Guard>} />
        <Route path="/mitre" element={<Guard perm="dashboard.view"><Mitre /></Guard>} />
        <Route path="/iocs" element={<Guard perm="iocs.view"><Iocs /></Guard>} />
        <Route path="/threat-intel" element={<Guard perm="threatintel.view"><ThreatIntel /></Guard>} />
        <Route path="/hosts" element={<Guard perm="agents.view"><Hosts /></Guard>} />
        <Route path="/users" element={<Guard perm="users.view"><Users /></Guard>} />
        <Route path="/audit" element={<Guard perm="audit.view"><Audit /></Guard>} />
        <Route path="/reports" element={<Guard perm="reports.view"><Reports /></Guard>} />
        <Route path="/api" element={<ApiDocs />} />
        <Route path="/settings" element={<Guard perm="settings.manage"><Settings /></Guard>} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/notifications" element={<Notifications />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
