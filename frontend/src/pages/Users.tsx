import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { Card, Skeleton } from '../components/ui';
import { useAuth } from '../store/auth';
import { useUi } from '../store/ui';

export default function Users() {
  const qc = useQueryClient(); const toast = useUi((s) => s.toast); const { can } = useAuth();
  const [name, setName] = useState(''); const [email, setEmail] = useState(''); const [role, setRole] = useState('analyst');
  const { data, isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: async () => {
      const body = (await api.get('/users')).data;
      const page = body.data;
      return (Array.isArray(page) ? page : (page?.data ?? [])) as { id: number; name: string; email: string; is_active: boolean; roles: ({ name: string } | string)[] }[];
    },
  });
  const create = useMutation({ mutationFn: async () => (await api.post('/users', { name, email, password: 'CipherSOC!changeme123', roles: [role] })).data, onSuccess: () => { toast('ok', 'User created'); setName(''); setEmail(''); void qc.invalidateQueries({ queryKey: ['users'] }); } });
  const rows = data ?? [];
  return (
    <Card title="Users & roles" sub="RBAC enforced server-side on every route">
      {can('users.manage') && <div className="filters"><input value={name} onChange={(e) => setName(e.target.value)} placeholder="Name" /><input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email" /><select value={role} onChange={(e) => setRole(e.target.value)}><option>admin</option><option>manager</option><option>analyst</option><option>viewer</option></select><button className="btn-primary btn-sm" onClick={() => create.mutate()}>Create</button></div>}
      {isLoading ? <Skeleton /> : <table className="tbl"><thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Active</th><th></th></tr></thead><tbody>{rows.map((u) => <tr key={u.id}><td>{u.name}</td><td>{u.email}</td><td>{(u.roles ?? []).map((r) => (typeof r === 'string' ? r : r.name)).join(', ')}</td><td>{u.is_active ? 'yes' : 'no'}</td><td>{can('users.manage') && <button className="btn-ghost btn-sm" onClick={async () => { await api.patch(`/users/${u.id}`, { is_active: !u.is_active }); toast('ok', 'User updated'); void qc.invalidateQueries({ queryKey: ['users'] }); }}>Toggle</button>}</td></tr>)}</tbody></table>}
    </Card>
  );
}
