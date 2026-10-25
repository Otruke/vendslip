import { useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Input, PageLoader } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { api, errorMessage, resetCsrf } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'

export default function AcceptInvite() {
  const { token } = useParams()
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const invite = useQuery({ queryKey: ['invite', token], queryFn: async () => (await api.get<{ email: string; role_label: string; merchant_name: string }>(`/invitations/${token}`)).data, retry: false })
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (invite.isLoading) return <PageLoader />
  if (invite.isError || !invite.data) {
    return <AuthLayout title="Invitation expired"><Alert tone="danger">This invitation is invalid or has expired. Ask the business owner to send a new one.</Alert></AuthLayout>
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const session = await apiPost<Session>(`/invitations/${token}/accept`, { name, password, password_confirmation: confirmation })
      resetCsrf()
      setSession(session)
      navigate('/dashboard', { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  return (
    <AuthLayout title={`Join ${invite.data.merchant_name}`} subtitle={`You’re invited as ${invite.data.role_label}.`}>
      <form onSubmit={submit} className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Email" value={invite.data.email} disabled />
        <Input label="Your name" required value={name} onChange={(e) => setName(e.target.value)} />
        <Input label="Password" type="password" autoComplete="new-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
        <Input label="Confirm password" type="password" autoComplete="new-password" required value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
        <Button type="submit" fullWidth size="lg" isLoading={busy}>Accept & continue</Button>
      </form>
    </AuthLayout>
  )
}
