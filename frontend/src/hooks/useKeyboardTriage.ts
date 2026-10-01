import { useEffect } from 'react';

// j/k navigate, a acknowledge, e escalate, r resolve — SOC keyboard triage.
export function useKeyboardTriage(count: number, selected: number | null, onSelect: (i: number | null) => void, onAction: (action: string) => void) {
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (e.key === 'j') onSelect(selected === null ? 0 : Math.min(count - 1, selected + 1));
      else if (e.key === 'k') onSelect(selected === null ? 0 : Math.max(0, selected - 1));
      else if (e.key === 'a') onAction('acknowledge');
      else if (e.key === 'e') onAction('escalate');
      else if (e.key === 'r') onAction('resolve');
      else if (e.key === 'Escape') onSelect(null);
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [count, selected, onSelect, onAction]);
}
