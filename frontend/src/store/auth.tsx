import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { api } from '../api/client';

export type SocUser = { id: number; name: string; email: string; timezone: string; roles: string[] };

type AuthCtx = {
  user: SocUser | null;
  permissions: string[];
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  can: (perm: string) => boolean;
  refresh: () => Promise<void>;
};

const Ctx = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<SocUser | null>(null);
  const [permissions, setPermissions] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    const token = localStorage.getItem('ciphersoc_token');
    if (!token) { setLoading(false); return; }
    try {
      const res = await api.get('/auth/me');
      setUser(res.data.data.user);
      setPermissions(res.data.data.permissions ?? []);
    } catch { localStorage.removeItem('ciphersoc_token'); setUser(null); }
    setLoading(false);
  }, []);

  useEffect(() => { void refresh(); }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post('/auth/login', { email, password });
    localStorage.setItem('ciphersoc_token', res.data.data.token);
    setUser(res.data.data.user);
    await refresh();
  }, [refresh]);

  const logout = useCallback(async () => {
    try { await api.post('/auth/logout'); } catch { /* noop */ }
    localStorage.removeItem('ciphersoc_token');
    setUser(null); setPermissions([]);
  }, []);

  const can = useCallback((perm: string) => permissions.includes(perm) || user?.roles.includes('admin') === true, [permissions, user]);
  const value = useMemo(() => ({ user, permissions, loading, login, logout, can, refresh }), [user, permissions, loading, login, logout, can, refresh]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useAuth(): AuthCtx {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error('useAuth outside provider');
  return ctx;
}
