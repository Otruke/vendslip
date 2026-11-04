import { useRef, type CSSProperties, type ReactNode } from 'react'
import { useLocation } from 'react-router-dom'
import { photos, slips } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Logo, Tagline } from '@/components/ui'
import { useScrollMotion } from '@/lib/motion'

/** Split screen: form left; VendSlip's own office photography and a real slip right. */
export function AuthLayout({ title, subtitle, children, footer }: { title: string; subtitle?: ReactNode; children: ReactNode; footer?: ReactNode }) {
  const shell = useRef<HTMLDivElement>(null)
  useScrollMotion(shell, useLocation().pathname)
  return (
    <div ref={shell} className="vs-motion grid min-h-dvh lg:grid-cols-2">
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
      <div className="relative isolate hidden overflow-hidden bg-vendslip-obsidian lg:flex lg:flex-col lg:items-center lg:justify-center">
        <Photo image={photos.wallDark} alt="" sizes="50vw" eager className="absolute inset-0 -z-10 h-full w-full object-cover" data-reveal="tear" />
        <div className="absolute inset-0 -z-10 bg-vendslip-obsidian/80" aria-hidden />
        <div className="w-[min(360px,70%)] rotate-[-2deg] drop-shadow-[0_30px_40px_rgba(0,0,0,0.45)]">
          <div data-reveal="feed" style={{ '--d': '350ms' } as CSSProperties}>
            <Photo image={slips.receiptMinimal} alt="A VendSlip receipt" sizes="360px" eager className="rounded-t-md" />
          </div>
        </div>
        <div className="mt-10" data-reveal="stamp" style={{ '--d': '1100ms' } as CSSProperties}><Tagline className="text-4xl text-white" /></div>
      </div>
    </div>
  )
}
