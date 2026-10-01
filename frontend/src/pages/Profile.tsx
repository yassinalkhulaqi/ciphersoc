import { useState } from 'react';
import { api } from '../api/client';
import { Card } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Profile() {
  const { user, refresh } = useAuth(); const toast = useUi((s) => s.toast);
  const [name, setName] = useState(user?.name ?? '');
  const [cur, setCur] = useState(''); const [pwd, setPwd] = useState(''); const [pwd2, setPwd2] = useState('');
  return (
    <div className="grid g2">
      <Card title="Profile"><div className="filters"><input value={name} onChange={(e) => setName(e.target.value)} placeholder="Display name" /><button className="btn-primary btn-sm" onClick={async () => { await api.put('/auth/profile', { name }); toast('ok', 'Profile updated'); void refresh(); }}>Save</button></div><p className="muted">{user?.email}</p></Card>
      <Card title="Change password"><div className="filters"><input type="password" value={cur} onChange={(e) => setCur(e.target.value)} placeholder="Current" /><input type="password" value={pwd} onChange={(e) => setPwd(e.target.value)} placeholder="New (8+ chars)" /><input type="password" value={pwd2} onChange={(e) => setPwd2(e.target.value)} placeholder="Confirm" /><button className="btn-primary btn-sm" onClick={async () => { await api.post('/auth/change-password', { current_password: cur, password: pwd, password_confirmation: pwd2 }); toast('ok', 'Password changed'); }}>Change</button></div></Card>
    </div>
  );
}
