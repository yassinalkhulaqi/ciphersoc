import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../api/client';
import { Card, JsonView, Skeleton } from '../components/ui';
import { useUi } from '../store/ui';

export default function Settings() {
  const toast = useUi((s) => s.toast);
  const [key, setKey] = useState('detection.threshold_default'); const [val, setVal] = useState('5');
  const { data, isLoading, refetch } = useQuery({ queryKey: ['settings'], queryFn: async () => (await api.get('/settings')).data.data });
  const save = async () => { await api.put('/settings', { settings: { [key]: { v: val } } }); toast('ok', 'Setting saved'); void refetch(); };
  return (
    <div className="grid g2">
      <Card title="Platform settings"><div className="filters"><input value={key} onChange={(e) => setKey(e.target.value)} /><input value={val} onChange={(e) => setVal(e.target.value)} /><button className="btn-primary btn-sm" onClick={save}>Save</button></div>{isLoading ? <Skeleton /> : <JsonView data={data} />}</Card>
      <Card title="Retention & ops" sub="Configurable, never silent-delete"><p className="muted">Event / audit / enrichment / report retention lives in app_settings and is honored by scheduled cleanup jobs only when explicitly configured.</p></Card>
    </div>
  );
}
