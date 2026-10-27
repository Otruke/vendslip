import type { ReactNode } from 'react'
import { photos, slips } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Logo, Tagline } from '@/components/ui'

/** Split screen: form left; NetSlip's own office photography and a real slip right. */
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
      <div className="relative isolate hidden overflow-hidden lg:flex lg:flex-col lg:items-center lg:justify-center">
        <Photo image={photos.wallDark} alt="" sizes="50vw" eager className="absolute inset-0 -z-10 h-full w-full object-cover" />
        <div className="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(22,53,64,0.55)_0%,rgba(22,53,64,0.88)_100%)]" aria-hidden />
        <div className="w-[min(360px,70%)] rotate-[-2deg] drop-shadow-[0_30px_40px_rgba(0,0,0,0.45)]">
          <Photo image={slips.receiptMinimal} alt="A NetSlip receipt" sizes="360px" eager className="rounded-t-md" />
        </div>
        <Tagline className="mt-10 text-4xl text-white" />
      </div>
    </div>
  )
}
