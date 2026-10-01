import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../store/auth';
import { MatrixRain } from '../components/soc';

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('admin@ciphersoc.local');
  const [password, setPassword] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  return (
    <div className="login-wrap">
      <form className="login" onSubmit={async (e) => { e.preventDefault(); setBusy(true); setErr(''); try { await login(email, password); nav('/'); } catch { setErr('Invalid credentials'); } setBusy(false); }}>
        <h1>◈ cipherSOC</h1>
        <p className="muted">Security Operations Center — analyst sign in</p>
        <MatrixRain />
        <input data-testid="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" />
        <input data-testid="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Password" />
        {err && <p style={{ color: '#FF2D55' }}>{err}</p>}
        <button className="btn-primary" disabled={busy} style={{ width: '100%', marginTop: 8 }}>{busy ? 'Signing in…' : 'Sign in'}</button>
        <p className="muted" style={{ fontSize: 12 }}>Demo: admin@ciphersoc.local · manager@ciphersoc.local · analyst@ciphersoc.local · viewer@ciphersoc.local</p>
        <p className="muted" style={{ fontSize: 12 }}><Link to="/register">Register</Link> · <Link to="/forgot-password">Forgot password?</Link></p>
      </form>
    </div>
  );
}
