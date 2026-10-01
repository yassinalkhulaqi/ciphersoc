import { create } from 'zustand';

type UiState = {
  sidebarOpen: boolean;
  toggleSidebar: () => void;
  toasts: { id: number; kind: string; text: string }[];
  toast: (kind: string, text: string) => void;
  dismiss: (id: number) => void;
};

let nextId = 1;
export const useUi = create<UiState>((set) => ({
  sidebarOpen: true,
  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
  toasts: [],
  toast: (kind, text) => set((s) => ({ toasts: [...s.toasts.slice(-4), { id: nextId++, kind, text }] })),
  dismiss: (id) => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
}));
