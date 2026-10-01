import axios from 'axios';

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1';

export const api = axios.create({ baseURL, timeout: 20000 });

api.interceptors.request.use((cfg) => {
  const token = localStorage.getItem('ciphersoc_token');
  if (token) cfg.headers.Authorization = `Bearer ${token}`;
  // Idempotency/correlation id for backend log tracing.
  cfg.headers['X-Request-ID'] = (globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`) as string;
  return cfg;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && !location.pathname.includes('/login')) {
      localStorage.removeItem('ciphersoc_token');
      location.href = '/login';
    }
    return Promise.reject(err);
  },
);

export type Paginated<T> = { success: boolean; data: T[]; meta: { current_page: number; per_page: number; total: number; last_page: number } };
export type One<T> = { success: boolean; data: T; message?: string | null };

export async function getPage<T>(url: string, params: Record<string, unknown> = {}): Promise<{ items: T[]; meta: Paginated<T>['meta'] }> {
  const res = await api.get(url, { params });
  const body = res.data;
  if (Array.isArray(body.data)) return { items: body.data as T[], meta: body.meta ?? { current_page: 1, per_page: 25, total: (body.data as T[]).length, last_page: 1 } };
  // Laravel default paginator shape fallback
  if (body.data?.data) return { items: body.data.data as T[], meta: { current_page: body.data.current_page, per_page: body.data.per_page, total: body.data.total, last_page: body.data.last_page } };
  return { items: [], meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 } };
}
