import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';

export default function ForgotPassword() {
  const [email, setEmail] = useState(''); const [done, setDone] = useState(false);
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    await api.post('/auth/forgot-password', { email });
    setDone(true);
  };
  return (
    <div className="login-wrap"><form className="login" onSubmit={submit}>
      <h1>◈ cipherSOC</h1><p className="muted">Reset access — check mailbox for link</p>
      {done ? <p>If the account exists, a reset link was sent.</p> : <>
        <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" type="email" required />
        <button className="btn-primary" type="submit" style={{ width: '100%' }}>Send reset link</button>
      </>}
      <p className="muted"><Link to="/login">Back to login</Link></p>
    </form></div>
  );
}
