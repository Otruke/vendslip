import { useEffect, type ReactNode } from 'react'
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { PageLoader, Toaster } from '@/components/ui'
import { fetchSession } from '@/hooks/queries'
import { ApiError, setUnauthorizedHandler } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

/** Loads the Sanctum session once for the whole app. */
export function SessionProvider({ children }: { children?: ReactNode }) {
  const status = useAuthStore((s) => s.status)
  const setSession = useAuthStore((s) => s.setSession)
  const setStatus = useAuthStore((s) => s.setStatus)
  const navigate = useNavigate()

  useEffect(() => {
    setUnauthorizedHandler(() => {
      setSession(null)
      navigate('/login', { replace: true })
    })
  }, [navigate, setSession])

  useEffect(() => {
    if (status !== 'idle') return
    setStatus('loading')
    fetchSession()
      .then((session) => setSession(session))
      .catch((e: unknown) => {
        if (!(e instanceof ApiError) || e.status !== 401) console.warn('Session check failed', e)
        setSession(null)
      })
  }, [status, setSession, setStatus])

  return (
    <>
      {children ?? <Outlet />}
      <Toaster />
    </>
  )
}

export function RequireAuth({ children }: { children: ReactNode }) {
  const { status, session } = useAuthStore()
  const location = useLocation()
  if (status === 'idle' || status === 'loading') return <PageLoader />
  if (!session) return <Navigate to={`/login?next=${encodeURIComponent(location.pathname + location.search)}`} replace />
  if (!session.merchant) return <Navigate to="/login?no_merchant=1" replace />
  const needsOnboarding = !session.merchant.onboarding_completed_at && session.user.role === 'Owner'
  if (needsOnboarding && !location.pathname.startsWith('/dashboard/onboarding')) return <Navigate to="/dashboard/onboarding" replace />
  return <>{children}</>
}

export function GuestOnly({ children }: { children: ReactNode }) {
  const { status, session } = useAuthStore()
  if (status === 'idle' || status === 'loading') return <PageLoader />
  if (session?.merchant) return <Navigate to="/dashboard" replace />
  return <>{children}</>
}
