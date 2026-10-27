import { Lock } from 'lucide-react'
import type { ReactNode } from 'react'
import { Button, Card } from '@/components/ui'

export function FeatureGate({ title, description, plan = 'Business Plus', icon }: { title: string; description: string; plan?: string; icon?: ReactNode }) {
  return (
    <Card variant="elevated" padding="lg" className="mx-auto max-w-xl rounded-2xl text-center">
      <span className="mx-auto block w-fit drop-shadow-[0_8px_10px_rgba(22,53,64,0.16)]" aria-hidden>
        <span className="serrated flex h-16 w-14 flex-col items-center gap-1.5 border border-b-0 border-border-subtle bg-white pt-3">
          <span className="text-netslip-primary [&>svg]:size-5">{icon ?? <Lock />}</span>
          <span className="h-px w-7 bg-border-subtle" />
          <span className="h-px w-5 bg-border-subtle" />
        </span>
      </span>
      <h2 className="mt-4 text-xl font-bold text-ink-primary">{title}</h2>
      <p className="mt-2 text-sm text-ink-secondary">{description}</p>
      <Button to="/dashboard/billing" className="mt-6">Upgrade to {plan}</Button>
    </Card>
  )
}
