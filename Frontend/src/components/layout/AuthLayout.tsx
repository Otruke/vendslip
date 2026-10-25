import type { ReactNode } from 'react'
import { Logo, Tagline } from '@/components/ui'
import { PaperSlipPreview } from '@/components/slip/PaperSlipPreview'
import { SAMPLE_SLIP } from '@/components/slip/sample'

/** Split screen: form left, deep-teal brand panel with a live slip right. */
export function AuthLayout({ title, subtitle, children, footer }: { title: string; subtitle?: ReactNode; children: ReactNode; footer?: ReactNode }) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-2">
      <div className="flex flex-col px-4 py-8 sm:px-10">
        <Logo />
        <div className="flex flex-1 items-center justify-center py-10">
          <div className="w-full max-w-sm animate-fade-up">
            <h1 className="text-2xl font-bold tracking-tight text-ink-primary">{title}</h1>
            {subtitle && <p className="mt-1.5 text-sm text-ink-secondary">{subtitle}</p>}
            <div className="mt-8">{children}</div>
            {footer && <div className="mt-6 text-center text-sm text-ink-secondary">{footer}</div>}
          </div>
        </div>
      </div>
      <div className="relative hidden overflow-hidden bg-netslip-deep lg:flex lg:flex-col lg:items-center lg:justify-center">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(255,255,255,0.10),transparent_50%),radial-gradient(circle_at_90%_90%,rgba(55,117,129,0.6),transparent_45%)]" aria-hidden />
        <div className="relative w-[340px] rotate-[-2deg]">
          <PaperSlipPreview slip={SAMPLE_SLIP} compact />
        </div>
        <Tagline className="relative mt-10 text-4xl text-white" />
      </div>
    </div>
  )
}
