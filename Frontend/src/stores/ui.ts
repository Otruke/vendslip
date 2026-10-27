import { create } from 'zustand'

export type ToastTone = 'success' | 'error' | 'warning' | 'info'
type Toast = { id: number; message: string; tone: ToastTone }

type UiState = {
  sidebarOpen: boolean
  toasts: Toast[]
  setSidebarOpen: (open: boolean) => void
  toast: (message: string, tone?: Toast['tone']) => void
  dismissToast: (id: number) => void
}

let nextId = 1

export const useUiStore = create<UiState>()((set) => ({
  sidebarOpen: false,
  toasts: [],
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  toast: (message, tone = 'success') => {
    const id = nextId++
    // Each toast runs its own countdown (paused on hover); see Toaster in components/ui/Feedback.tsx.
    set((s) => ({ toasts: [...s.toasts.slice(-3), { id, message, tone }] }))
  },
  dismissToast: (id) => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
}))

export const toast = (message: string, tone?: Toast['tone']) => useUiStore.getState().toast(message, tone)
