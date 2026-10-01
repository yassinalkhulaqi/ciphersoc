import { useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';

export function CountUp({ value, duration = 0.8 }: { value: number; duration?: number }) {
  const [n, setN] = useState(0);
  useEffect(() => {
    let raf = 0; const t0 = performance.now();
    const tick = (t: number) => {
      const p = Math.min(1, (t - t0) / (duration * 1000));
      setN(Math.round(value * (1 - Math.pow(1 - p, 3))));
      if (p < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, [value, duration]);
  return <span>{n.toLocaleString()}</span>;
}

export function Spark({ data, w = 110, h = 32, color = '#00F0FF' }: { data: number[]; w?: number; h?: number; color?: string }) {
  if (!data || data.length === 0) return null;
  const max = Math.max(...data, 1);
  const pts = data.map((v, i) => `${(i / Math.max(1, data.length - 1)) * w},${h - (v / max) * (h - 4) - 2}`).join(' ');
  return <svg width={w} height={h}><polyline points={pts} fill="none" stroke={color} strokeWidth={2} /></svg>;
}

export function Donut({ parts }: { parts: { label: string; value: number; color: string }[] }) {
  const total = Math.max(1, parts.reduce((s, p) => s + p.value, 0));
  let acc = 0;
  const segs = parts.map((p) => {
    const from = (acc / total) * 360; acc += p.value; const to = (acc / total) * 360;
    return `${p.color} ${from}deg ${to}deg`;
  });
  return (
    <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
      <div style={{ width: 120, height: 120, borderRadius: '50%', background: `conic-gradient(${segs.join(',')})`, boxShadow: '0 0 24px rgba(0,240,255,.2)' }} />
      <div>{parts.map((p) => <div key={p.label} style={{ fontSize: 12 }}><span style={{ color: p.color }}>●</span> {p.label}: <b>{p.value}</b></div>)}</div>
    </div>
  );
}

export function AttackMap({ points }: { points: { ip: string; lat: number; lng: number; count: number }[] }) {
  // Stylized grid world map (offline, no external tiles).
  const cells = Array.from({ length: 18 * 8 }, (_, i) => i);
  const hot = new Set(points.slice(0, 24).map((_, i) => (i * 37) % cells.length));
  return (
    <div>
      <div className="map-grid">{cells.map((c) => <div key={c} className={`map-dot${hot.has(c) ? ' hot' : c % 29 === 0 ? ' warm' : ''}`} />)}</div>
      <div className="muted" style={{ marginTop: 6, fontSize: 12 }}>{points.slice(0, 5).map((p) => p.ip).join(' · ') || 'No active sources'}</div>
    </div>
  );
}

export function Stagger({ children, delay = 0.05 }: { children: React.ReactNode[]; delay?: number }) {
  return <>{children.map((c, i) => <motion.div key={i} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * delay, duration: 0.3 }}>{c}</motion.div>)}</>;
}

export function MatrixRain({ run = true }: { run?: boolean }) {
  const ref = useRef<HTMLCanvasElement>(null);
  useEffect(() => {
    if (!run) return;
    const cv = ref.current; if (!cv) return;
    const ctx = cv.getContext('2d'); if (!ctx) return;
    cv.width = 380; cv.height = 120;
    const cols = Math.floor(cv.width / 14); const drops = Array(cols).fill(0);
    let raf = 0;
    const draw = () => {
      ctx.fillStyle = 'rgba(5,10,20,0.12)'; ctx.fillRect(0, 0, cv.width, cv.height);
      ctx.fillStyle = '#00F0FF'; ctx.font = '12px monospace';
      drops.forEach((y: number, i: number) => {
        ctx.fillText(String.fromCharCode(0x30A0 + Math.random() * 96), i * 14, y * 14);
        drops[i] = y * 14 > cv.height && Math.random() > 0.975 ? 0 : y + 1;
      });
      raf = requestAnimationFrame(draw);
    };
    raf = requestAnimationFrame(draw);
    const t = setTimeout(() => cancelAnimationFrame(raf), 2000);
    return () => { cancelAnimationFrame(raf); clearTimeout(t); };
  }, [run]);
  if (!run) return null;
  return <canvas ref={ref} style={{ width: '100%', borderRadius: 8, opacity: 0.8 }} />;
}
