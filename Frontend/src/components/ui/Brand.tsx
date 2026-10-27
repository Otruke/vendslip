import { Link } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { initials } from '@/lib/format'

export function Logo({ variant = 'dark', className, to = '/' }: { variant?: 'dark' | 'light'; className?: string; to?: string }) {
  return (
    <Link to={to} className={cn('inline-flex items-center gap-2', className)} aria-label="NetSlip home">
      <img src={variant === 'light' ? '/assets/brand/netslip-icon-white.png' : '/assets/brand/netslip-icon.png'} alt="" className="h-7 w-auto" />
      <span className={cn('text-xl font-extrabold tracking-tight', variant === 'light' ? 'text-white' : 'text-netslip-deep')}>NetSlip</span>
    </Link>
  )
}

export function Tagline({ className }: { className?: string }) {
  return <span className={cn('font-script text-netslip-primary', className)}>Close the sale. Send the slip</span>
}

export function Avatar({ name, className }: { name: string; className?: string }) {
  return (
    <span className={cn('inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-netslip-deep text-xs font-bold text-white', className)} aria-hidden>
      {initials(name)}
    </span>
  )
}
