import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Input } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'

export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const res = await apiPost<{ message: string }>('/auth/forgot-password', { email })
      setSent(res.message)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthLayout title="Reset your password" subtitle="We’ll email you a secure reset link." footer={<Link to="/login" className="font-semibold text-netslip-primary">Back to sign in</Link>}>
      {sent ? <Alert tone="success">{sent}</Alert> : (
        <form onSubmit={submit} className="space-y-4">
          {error && <Alert tone="danger">{error}</Alert>}
          <Input label="Email" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
          <Button type="submit" fullWidth size="lg" isLoading={busy}>Send reset link</Button>
        </form>
      )}
    </AuthLayout>
  )
}
