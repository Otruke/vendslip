import { zodResolver } from '@hookform/resolvers/zod'
import { Mail } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Checkbox, Input } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { resetCsrf } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { safeNext } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'

const schema = z.object({
  email: z.email('Enter a valid email'),
  password: z.string().min(1, 'Enter your password'),
  remember: z.boolean(),
})
type Values = z.infer<typeof schema>

export default function Login() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const [formError, setFormError] = useState<string | null>(params.get('no_merchant') ? 'This account has no business workspace. Platform admins sign in at /admin.' : null)
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '', remember: true } })

  const onSubmit = async (values: Values) => {
    setFormError(null)
    try {
      const res = await apiPost<Session | { two_factor: true }>('/auth/login', values)
      resetCsrf()
      if ('two_factor' in res) {
        navigate(`/verify-2fa?next=${encodeURIComponent(safeNext(params.get('next')))}`)
        return
      }
      setSession(res)
      navigate(safeNext(params.get('next')), { replace: true })
    } catch (e) {
      setFormError(applyServerErrors(e, setError, ['email', 'password']))
    }
  }

  return (
    <AuthLayout title="Welcome back" subtitle="Sign in to send slips and track payments." footer={<>New to NetSlip? <Link to="/register" className="font-semibold text-netslip-primary">Create an account</Link></>}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Input label="Email" type="email" autoComplete="username" prefixIcon={<Mail />} error={errors.email?.message} {...register('email')} />
        <Input label="Password" type="password" autoComplete="current-password" error={errors.password?.message} labelAction={<Link to="/forgot-password" className="text-xs font-semibold text-netslip-primary">Forgot?</Link>} {...register('password')} />
        <Checkbox label="Keep me signed in" {...register('remember')} />
        <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>Sign in</Button>
      </form>
    </AuthLayout>
  )
}
