import { useState } from 'react';
import { api } from '../api/client';
import { Card } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Profile() {
  const { user, refresh } = useAuth(); const toast = useUi((s) => s.toast);
  const [name, setName] = useState(user?.name ?? '');
  const [cur, setCur] = useState(''); const [pwd, setPwd] = useState(''); const [pwd2, setPwd2] = useState('');
  const [mfaUri, setMfaUri] = useState(''); const [mfaCode, setMfaCode] = useState('');
  const [tokens, setTokens] = useState<{ id: number; name: string }[]>([]); const [tokenName, setTokenName] = useState('soc-cli'); const [newToken, setNewToken] = useState('');
  const loadTokens = async () => { const r = await api.get('/auth/tokens'); setTokens(r.data.data); };
  useState(() => { void loadTokens(); });
  return (
    <div className="grid g2">
      <Card title="Profile"><div className="filters"><input value={name} onChange={(e) => setName(e.target.value)} placeholder="Display name" /><button className="btn-primary btn-sm" onClick={async () => { await api.put('/auth/profile', { name }); toast('ok', 'Profile updated'); void refresh(); }}>Save</button></div><p className="muted">{user?.email}</p></Card>
      <Card title="Change password"><div className="filters"><input type="password" value={cur} onChange={(e) => setCur(e.target.value)} placeholder="Current" /><input type="password" value={pwd} onChange={(e) => setPwd(e.target.value)} placeholder="New (8+ chars)" /><input type="password" value={pwd2} onChange={(e) => setPwd2(e.target.value)} placeholder="Confirm" /><button className="btn-primary btn-sm" onClick={async () => { await api.post('/auth/change-password', { current_password: cur, password: pwd, password_confirmation: pwd2 }); toast('ok', 'Password changed'); }}>Change</button></div></Card>
      <Card title="MFA (TOTP)" sub="Scan URI in authenticator, verify code to enable"><div className="filters"><button className="btn-ghost btn-sm" onClick={async () => { const r = await api.get('/auth/mfa/setup'); setMfaUri(r.data.data.uri); }}>Setup</button><input value={mfaCode} onChange={(e) => setMfaCode(e.target.value)} placeholder="6-digit code" /><button className="btn-primary btn-sm" onClick={async () => { await api.post('/auth/mfa/enable', { code: mfaCode }); toast('ok', 'MFA enabled'); }}>Enable</button><button className="btn-ghost btn-sm" onClick={async () => { await api.post('/auth/mfa/disable'); toast('ok', 'MFA disabled'); }}>Disable</button></div>{mfaUri && <p className="kv" style={{ wordBreak: 'break-all' }}>{mfaUri}</p>}</Card>
      <Card title="API keys" sub="Personal tokens for CLI/automation"><div className="filters"><input value={tokenName} onChange={(e) => setTokenName(e.target.value)} placeholder="Token name" /><button className="btn-primary btn-sm" onClick={async () => { const r = await api.post('/auth/tokens', { name: tokenName }); setNewToken(r.data.data.token); toast('ok', 'Token created — copy now'); void loadTokens(); }}>Create</button></div>{newToken && <p className="kv" style={{ wordBreak: 'break-all' }}>{newToken}</p>}{tokens.map((t) => <div key={t.id} className="field"><span>{t.name}</span><button className="btn-ghost btn-sm" onClick={async () => { await api.delete(`/auth/tokens/${t.id}`); void loadTokens(); }}>Revoke</button></div>)}</Card>
    </div>
  );
}
