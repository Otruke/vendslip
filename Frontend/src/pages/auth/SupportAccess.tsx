import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, PageLoader } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage, resetCsrf } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'

/**
 * Landing point for a VendSlip Super-Admin after the business owner approved support access.
 * The one-time token arrives in the URL fragment (never sent to a server or in a Referer); it is
 * wiped from the address bar before the request, then exchanged once for a support session.
 */
export default function SupportAccess() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const setSession = useAuthStore((s) => s.setSession)
  // Read the token on first render (pure, so StrictMode's double render sees the same value)…
  const [token] = useState(() => {
    const value = new URLSearchParams(window.location.hash.slice(1)).get('token') ?? ''
    return /^[A-Za-z0-9]{64}$/.test(value) ? value : null
  })
  const [error, setError] = useState<string | null>(token ? null : 'This support link is incomplete. Verify the owner’s code again from the admin portal.')
  const started = useRef(false)

  // …then strip it from the address bar so it isn't left in history or shown on screen.
  useEffect(() => {
    if (window.location.hash) window.history.replaceState(null, '', window.location.pathname)
  }, [])

  useEffect(() => {
    if (!token || started.current) return // StrictMode runs effects twice; the token only works once
    started.current = true
    void (async () => {
      try {
        resetCsrf()
        const session = await apiPost<Session>('/auth/support-access', { token })
        resetCsrf()
        queryClient.clear()
        setSession(session)
        navigate('/dashboard', { replace: true })
      } catch (e) {
        setError(errorMessage(e))
      }
    })()
  }, [navigate, queryClient, setSession, token])

  if (!error) return <PageLoader />

  return (
    <AuthLayout title="Support link didn’t work" subtitle="Support access needs a fresh code from the business owner.">
      <Alert tone="danger">{error}</Alert>
    </AuthLayout>
  )
}
