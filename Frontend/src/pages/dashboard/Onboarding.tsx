import { ArrowLeft, ArrowRight, Bot, Building2, Check, Coins, Palette } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Button, Card, Logo } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { useAuthStore, useEntitlement } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { Session } from '@/types/api'
import { PricingCards } from '../public/PricingCards'
import Channels from './Channels'
import Templates from './Templates'
import { BrandingSettings, BusinessSettings } from './Settings'

const STEPS = [
  { key: 'business', title: 'Business & VAT', icon: <Building2 /> },
  { key: 'brand', title: 'Brand & template', icon: <Palette /> },
  { key: 'channels', title: 'Connect a chat', icon: <Bot /> },
  { key: 'plan', title: 'Plan or credits', icon: <Coins /> },
] as const

export default function Onboarding() {
  const [step, setStep] = useState(0)
  const [finishing, setFinishing] = useState(false)
  const setSession = useAuthStore((s) => s.setSession)
  const branding = useEntitlement('custom_branding')
  const navigate = useNavigate()

  const finish = async () => {
    setFinishing(true)
    try {
      setSession(await apiPost<Session>('/onboarding/complete'))
      toast('You’re all set. Send your first slip!')
      navigate('/dashboard/composer', { replace: true })
    } catch (e) {
      toast(errorMessage(e), 'error')
      setFinishing(false)
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Logo to="/dashboard/onboarding" />
          <h1 className="mt-4 text-2xl font-bold">Let’s set up your slips</h1>
          <p className="text-sm text-ink-secondary">Four quick steps. You can change everything later.</p>
        </div>
        <Button variant="ghost" onClick={finish} isLoading={finishing}>Skip for now</Button>
      </div>

      <ol className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {STEPS.map((s, i) => (
          <li key={s.key}>
            <button type="button" onClick={() => setStep(i)} className={cn('flex w-full items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-medium transition', i === step ? 'border-netslip-primary bg-netslip-tint text-netslip-deep' : i < step ? 'border-border-subtle bg-white text-ink-primary' : 'border-border-subtle bg-white text-ink-muted')}>
              <span className={cn('inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs [&>svg]:size-3.5', i < step ? 'bg-tone-settled text-white' : i === step ? 'bg-netslip-deep text-white' : 'bg-surface-subtle')}>{i < step ? <Check /> : i + 1}</span>
              <span className="truncate">{s.title}</span>
            </button>
          </li>
        ))}
      </ol>

      <div className="animate-fade-up" key={step}>
        {step === 0 && <BusinessSettings compact onSaved={() => setStep(1)} />}
        {step === 1 && (
          <div className="space-y-6">
            {branding ? <BrandingSettings /> : <Card variant="tinted" padding="sm" className="text-sm text-netslip-deep">Free slips use NetSlip teal. Upgrade to Pro to add your logo and colours.</Card>}
            <Templates />
          </div>
        )}
        {step === 2 && <Channels />}
        {step === 3 && <PricingCards ctaTo="/dashboard/billing" />}
      </div>

      <div className="flex justify-between border-t border-border-subtle pt-6">
        <Button variant="ghost" leftIcon={<ArrowLeft className="size-4" />} disabled={step === 0} onClick={() => setStep((s) => s - 1)}>Back</Button>
        {step < STEPS.length - 1
          ? <Button rightIcon={<ArrowRight className="size-4" />} onClick={() => setStep((s) => s + 1)}>Continue</Button>
          : <Button isLoading={finishing} onClick={finish} rightIcon={<Check className="size-4" />}>Finish setup</Button>}
      </div>
    </div>
  )
}
