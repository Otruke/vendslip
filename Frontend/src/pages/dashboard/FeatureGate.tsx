import { Lock } from 'lucide-react'
import type { ReactNode } from 'react'
import { Button, Card } from '@/components/ui'

export function FeatureGate({ title, description, plan = 'Business Plus', icon }: { title: string; description: string; plan?: string; icon?: ReactNode }) {
  return (
    <Card variant="elevated" padding="lg" className="mx-auto max-w-xl rounded-2xl text-center">
      <span className="mx-auto inline-flex size-12 items-center justify-center rounded-xl bg-netslip-tint text-netslip-deep [&>svg]:size-6">{icon ?? <Lock />}</span>
      <h2 className="mt-4 text-xl font-bold text-ink-primary">{title}</h2>
      <p className="mt-2 text-sm text-ink-secondary">{description}</p>
      <Button to="/dashboard/billing" className="mt-6">Upgrade to {plan}</Button>
    </Card>
  )
}
