import { ShieldCheck } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Input } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage, resetCsrf } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'
import { safeNext } from '@/lib/navigation'

export default function TwoFactor() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const [code, setCode] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const session = await apiPost<Session>('/auth/two-factor', { code })
      resetCsrf()
      setSession(session)
      navigate(safeNext(params.get('next')), { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  return (
    <AuthLayout title="Two-step verification" subtitle="Enter the 6-digit code from your authenticator app.">
      <form onSubmit={submit} className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Authentication code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} autoFocus prefixIcon={<ShieldCheck />} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} className="tabular text-lg tracking-[0.4em]" />
        <Button type="submit" fullWidth size="lg" isLoading={busy} disabled={code.length !== 6}>Verify</Button>
      </form>
    </AuthLayout>
  )
}
