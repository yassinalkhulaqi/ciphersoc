import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { MatrixRain } from '../components/soc';

export default function Register() {
  const nav = useNavigate();
  const [name, setName] = useState(''); const [email, setEmail] = useState('');
  const [pw, setPw] = useState(''); const [pw2, setPw2] = useState(''); const [err, setErr] = useState('');
  const submit = async (e: React.FormEvent) => {
    e.preventDefault(); setErr('');
    try {
      const r = await api.post('/auth/register', { name, email, password: pw, password_confirmation: pw2 });
      localStorage.setItem('ciphersoc_token', r.data.data.token);
      nav('/');
    } catch (ex: unknown) {
      setErr((ex as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Registration failed (min 8, upper+lower+number)');
    }
  };
  return (
    <div className="login-wrap"><form className="login" onSubmit={submit}>
      <h1>◈ cipherSOC</h1><p className="muted">Analyst registration — viewer by default</p>
      <MatrixRain />
      <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Full name" required />
      <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" type="email" required />
      <input value={pw} onChange={(e) => setPw(e.target.value)} placeholder="Password (8+, Aa1)" type="password" required />
      <input value={pw2} onChange={(e) => setPw2(e.target.value)} placeholder="Confirm password" type="password" required />
      {err && <p style={{ color: '#FF2D55' }}>{err}</p>}
      <button className="btn-primary" type="submit" style={{ width: '100%' }}>Register</button>
      <p className="muted"><Link to="/login">Back to login</Link></p>
    </form></div>
  );
}
