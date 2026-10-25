import { create } from 'zustand'
import type { Session } from '@/types/api'

type AuthState = {
  session: Session | null
  status: 'idle' | 'loading' | 'authenticated' | 'guest'
  setSession: (session: Session | null) => void
  setStatus: (status: AuthState['status']) => void
}

export const useAuthStore = create<AuthState>()((set) => ({
  session: null,
  status: 'idle',
  setSession: (session) => set({ session, status: session ? 'authenticated' : 'guest' }),
  setStatus: (status) => set({ status }),
}))

/** Permission helper mirroring Backend/app/Enums/Permission.php */
export function useCan(permission: string): boolean {
  return useAuthStore((s) => s.session?.user.permissions.includes(permission) ?? false)
}

export function useMerchant() {
  return useAuthStore((s) => s.session?.merchant ?? null)
}

export function useEntitlement(feature: keyof NonNullable<Session['entitlements']>): boolean {
  return useAuthStore((s) => s.session?.entitlements[feature] ?? false)
}
