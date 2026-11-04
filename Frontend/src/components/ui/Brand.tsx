import { useState } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { initials } from '@/lib/format'

export function Logo({ variant = 'dark', className, to = '/' }: { variant?: 'dark' | 'light'; className?: string; to?: string }) {
  return (
    <Link to={to} className={cn('inline-flex items-center gap-2', className)} aria-label="VendSlip home">
      <img src={variant === 'light' ? '/assets/brand/vendslip-icon-white.png' : '/assets/brand/vendslip-icon.png'} alt="" className="h-7 w-auto" />
      <span className={cn('text-xl font-extrabold tracking-tight', variant === 'light' ? 'text-white' : 'text-vendslip-deep')}>VendSlip</span>
    </Link>
  )
}

export function Tagline({ className }: { className?: string }) {
  return <span className={cn('font-script text-vendslip-primary', className)}>Close the sale. Send the slip</span>
}

/**
 * A person's photo, or their initials when they have none. Photos come from an authenticated API
 * route (same-origin, session cookie), so a failed load (signed out, removed) falls back quietly.
 */
export function Avatar({ name, src, className }: { name: string; src?: string | null; className?: string }) {
  const [failed, setFailed] = useState<string | null>(null)
  const base = 'inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-vendslip-deep text-xs font-bold text-white'
  if (src && failed !== src) {
    return <img src={src} alt="" onError={() => setFailed(src)} className={cn(base, 'object-cover', className)} />
  }
  return (
    <span className={cn(base, className)} aria-hidden>
      {initials(name)}
    </span>
  )
}
