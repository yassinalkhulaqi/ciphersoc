import '@testing-library/jest-dom';

// jsdom with an opaque origin ships a broken localStorage — replace it when unusable.
try {
  const probe = (globalThis as unknown as { localStorage?: Storage }).localStorage;
  probe?.getItem('__probe__');
} catch {
  const store = new Map<string, string>();
  Object.defineProperty(globalThis, 'localStorage', {
    value: {
      getItem: (k: string) => store.get(k) ?? null,
      setItem: (k: string, v: string) => { store.set(k, String(v)); },
      removeItem: (k: string) => { store.delete(k); },
      clear: () => store.clear(),
    },
    configurable: true,
  });
}
