import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { Alert, Button, Input } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { resetCsrf } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'

const schema = z
  .object({
    business_name: z.string().trim().min(2, 'Enter your business name').max(160),
    name: z.string().trim().min(2, 'Enter your name').max(120),
    email: z.email('Enter a valid email'),
    phone: z.string().trim().max(32).optional(),
    password: z.string().min(8, 'At least 8 characters').regex(/[A-Za-z]/, 'Include a letter').regex(/\d/, 'Include a number'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Passwords don’t match' })
type Values = z.infer<typeof schema>
const FIELDS = ['business_name', 'name', 'email', 'phone', 'password', 'password_confirmation'] as const

export default function Register() {
  const navigate = useNavigate()
  const setSession = useAuthStore((s) => s.setSession)
  const [formError, setFormError] = useState<string | null>(null)
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<Values>({ resolver: zodResolver(schema) })

  const onSubmit = async (values: Values) => {
    setFormError(null)
    try {
      const session = await apiPost<Session>('/auth/register', { ...values, phone: values.phone || null })
      resetCsrf()
      setSession(session)
      navigate('/dashboard/onboarding', { replace: true })
    } catch (e) {
      setFormError(applyServerErrors(e, setError, FIELDS))
    }
  }

  return (
    <AuthLayout title="Start free" subtitle="15 slips a month on us. No card needed." footer={<>Already have an account? <Link to="/login" className="font-semibold text-netslip-primary">Sign in</Link></>}>
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
        {formError && <Alert tone="danger">{formError}</Alert>}
        <Input label="Business name" placeholder="Ada’s Closet" error={errors.business_name?.message} {...register('business_name')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Your name" autoComplete="name" error={errors.name?.message} {...register('name')} />
          <Input label="Phone" type="tel" autoComplete="tel" placeholder="0803…" error={errors.phone?.message} {...register('phone')} />
        </div>
        <Input label="Email" type="email" autoComplete="email" error={errors.email?.message} {...register('email')} />
        <Input label="Password" type="password" autoComplete="new-password" error={errors.password?.message} helperText="8+ characters with a letter and a number." {...register('password')} />
        <Input label="Confirm password" type="password" autoComplete="new-password" error={errors.password_confirmation?.message} {...register('password_confirmation')} />
        <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>Create account</Button>
        <p className="text-center text-xs text-ink-muted">By continuing you agree to fair use and NDPA-compliant data processing.</p>
      </form>
    </AuthLayout>
  )
}
