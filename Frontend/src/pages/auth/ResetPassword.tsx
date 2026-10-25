import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Input } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'

export default function ResetPassword() {
  const [params] = useSearchParams()
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [done, setDone] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (password !== confirmation) { setError('Passwords don’t match.'); return }
    setBusy(true)
    setError(null)
    try {
      await apiPost('/auth/reset-password', { token: params.get('token'), email: params.get('email'), password, password_confirmation: confirmation })
      setDone(true)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthLayout title="Choose a new password" footer={<Link to="/login" className="font-semibold text-netslip-primary">Back to sign in</Link>}>
      {done ? <Alert tone="success" title="Password updated">You can now sign in with your new password.</Alert> : (
        <form onSubmit={submit} className="space-y-4">
          {error && <Alert tone="danger">{error}</Alert>}
          <Input label="New password" type="password" autoComplete="new-password" required value={password} onChange={(e) => setPassword(e.target.value)} helperText="8+ characters with a letter and a number." />
          <Input label="Confirm password" type="password" autoComplete="new-password" required value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
          <Button type="submit" fullWidth size="lg" isLoading={busy}>Update password</Button>
        </form>
      )}
    </AuthLayout>
  )
}
