import { useState } from 'react';
import type { ReactNode } from 'react';

const sevColor: Record<string, string> = { critical: '#ef4444', high: '#f97316', medium: '#eab308', low: '#22c55e', info: '#3b82f6' };
const statusColor: Record<string, string> = { new: '#3b82f6', acknowledged: '#8b5cf6', investigating: '#eab308', escalated: '#f97316', containment: '#f97316', eradication: '#ec4899', recovery: '#14b8a6', resolved: '#22c55e', closed: '#6b7280', false_positive: '#6b7280', open: '#3b82f6' };

export function SeverityBadge({ value }: { value?: string }) {
  const v = (value ?? 'info').toLowerCase();
  return <span className="badge" style={{ background: sevColor[v] ?? '#6b7280' }}>{v.toUpperCase()}</span>;
}
export function StatusBadge({ value }: { value?: string }) {
  const v = (value ?? 'new').toLowerCase();
  return <span className="badge badge-outline" style={{ borderColor: statusColor[v] ?? '#6b7280', color: statusColor[v] ?? '#9ca3af' }}>{String(value ?? '').replace(/_/g, ' ').toUpperCase()}</span>;
}
export function Card({ title, sub, children, right }: { title?: string; sub?: string; children: ReactNode; right?: ReactNode }) {
  return <section className="card"><header className="card-h">{title && <div><h3>{title}</h3>{sub && <p className="muted">{sub}</p>}</div>}{right}</header><div className="card-b">{children}</div></section>;
}
export function EmptyState({ title, hint }: { title: string; hint?: string }) {
  return <div className="empty"><div className="empty-t">{title}</div>{hint && <div className="muted">{hint}</div>}</div>;
}
export function Skeleton({ rows = 5 }: { rows?: number }) {
  return <div>{Array.from({ length: rows }).map((_, i) => <div key={i} className="skel" />)}</div>;
}
export function Pagination({ meta, onPage }: { meta: { current_page: number; last_page: number; total: number }; onPage: (p: number) => void }) {
  return <div className="pager"><span className="muted">Total {meta.total} · Page {meta.current_page}/{meta.last_page}</span>
    <div><button disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Prev</button>
    <button disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next</button></div></div>;
}
export function Confirm({ open, title, body, onCancel, onConfirm }: { open: boolean; title: string; body?: string; onCancel: () => void; onConfirm: () => void }) {
  if (!open) return null;
  return <div className="modal-bg" onClick={onCancel}><div className="modal" onClick={(e) => e.stopPropagation()}><h3>{title}</h3>{body && <p className="muted">{body}</p>}<div className="row-end"><button className="btn-ghost" onClick={onCancel}>Cancel</button><button className="btn-danger" onClick={onConfirm}>Confirm</button></div></div></div>;
}
export function JsonView({ data, maxHeight = 320 }: { data: unknown; maxHeight?: number }) {
  const [copied, setCopied] = useState(false);
  const text = JSON.stringify(data, null, 2);
  return <div className="json-wrap"><div className="row-end"><button className="btn-ghost btn-sm" onClick={() => { void navigator.clipboard.writeText(text); setCopied(true); setTimeout(() => setCopied(false), 1200); }}>{copied ? 'Copied' : 'Copy JSON'}</button></div><pre className="json" style={{ maxHeight }}>{text}</pre></div>;
}
export function RiskFactors({ score, factors }: { score?: number; factors?: { factor: string; points: number }[] }) {
  return <div className="risk"><div className="risk-score">{score ?? '—'}</div><div>{(factors ?? []).map((f, i) => <div key={i} className="risk-f"><span>{f.factor}</span><b>+{f.points}</b></div>)}{(!factors || factors.length === 0) && <span className="muted">No factor breakdown recorded.</span>}</div></div>;
}
export function Field({ label, children }: { label: string; children: ReactNode }) {
  return <div className="field"><div className="field-l">{label}</div><div className="field-v">{children ?? <span className="muted">—</span>}</div></div>;
}
