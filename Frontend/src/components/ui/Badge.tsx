import { BadgeCheck, CircleCheck, Clock, FilePen, Lock, TriangleAlert } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

/**
 * Status "ledger tags" (styles in index.css → .ns-tag). A thin-bordered tag on white with a
 * diamond punch mark: filled when settled or final, hollow while still open. NetSlip's own
 * tones, not pastel bg-*-50 pills.
 */
type Tone = 'settled' | 'open' | 'partial' | 'neutral' | 'brand' | 'info' | 'whatsapp' | 'telegram' | 'instagram' | 'facebook'
type Def = { tone: Tone; label: string; solid?: boolean; inverse?: boolean; icon?: ReactNode; pulse?: boolean }

const STATUS: Record<string, Def> = {
  PAID: { tone: 'settled', label: 'Paid', solid: true, icon: <CircleCheck /> },
  PARTIAL: { tone: 'partial', label: 'Part paid', icon: <Clock /> },
  UNPAID: { tone: 'open', label: 'Unpaid', icon: <Clock /> },
  OVERDUE: { tone: 'open', label: 'Overdue', icon: <TriangleAlert />, pulse: true },
  DRAFT: { tone: 'neutral', label: 'Draft' },
  VOID: { tone: 'neutral', label: 'Void', solid: true },
  IMMUTABLE: { tone: 'brand', label: 'Immutable', solid: true, icon: <Lock /> },
  MUTABLE_WITH_AUDIT: { tone: 'info', label: 'Mutable · Audited', icon: <FilePen /> },
  MUTABLE_UNRESTRICTED: { tone: 'info', label: 'Mutable', icon: <FilePen /> },
  VERIFIED: { tone: 'settled', label: 'Verified authentic', solid: true, icon: <BadgeCheck /> },
  SUCCESSFUL: { tone: 'settled', label: 'Successful', solid: true },
  PENDING: { tone: 'partial', label: 'Pending', pulse: true },
  FAILED: { tone: 'open', label: 'Failed', solid: true },
  CANCELLED: { tone: 'neutral', label: 'Cancelled', solid: true },
  ACTIVE: { tone: 'settled', label: 'Active', solid: true },
  SUSPENDED: { tone: 'open', label: 'Suspended', solid: true },
  FREE: { tone: 'neutral', label: 'Free' },
  PRO: { tone: 'brand', label: 'Pro' },
  BUSINESS_PLUS: { tone: 'brand', label: 'Business Plus', solid: true, inverse: true },
  WHATSAPP: { tone: 'whatsapp', label: 'WhatsApp', solid: true },
  TELEGRAM: { tone: 'telegram', label: 'Telegram', solid: true },
  INSTAGRAM: { tone: 'instagram', label: 'Instagram', solid: true },
  FACEBOOK: { tone: 'facebook', label: 'Facebook', solid: true },
  WEB: { tone: 'neutral', label: 'Web', solid: true },
  RECEIPT: { tone: 'brand', label: 'Receipt', solid: true },
  INVOICE: { tone: 'brand', label: 'Invoice', solid: true, inverse: true },
  CREDIT_NOTE: { tone: 'partial', label: 'Credit note', solid: true },
}

export function StatusBadge({ status, label, size = 'md', showDot = true, showIcon = false, className }: {
  status: string
  label?: ReactNode
  size?: 'sm' | 'md'
  showDot?: boolean
  showIcon?: boolean
  className?: string
}) {
  const def: Def = STATUS[status] ?? { tone: 'neutral', label: status.replace(/_/g, ' ').toLowerCase().replace(/^\w/, (c) => c.toUpperCase()) }
  return (
    <span
      className={cn('ns-tag', `ns-tag--${def.tone}`, size === 'sm' && 'ns-tag--sm', def.inverse && 'ns-tag--inverse', className)}
      data-fill={def.solid ? 'solid' : 'hollow'}
      data-pulse={def.pulse ? '' : undefined}
    >
      {showIcon && def.icon ? <span className="[&>svg]:size-3.5">{def.icon}</span> : showDot ? <span className="ns-tag__mark" aria-hidden /> : null}
      {label ?? def.label}
    </span>
  )
}

export function Pill({ children, className }: { children: ReactNode; className?: string }) {
  return <span className={cn('inline-flex items-center gap-1.5 rounded-md border border-border-subtle bg-white px-2.5 py-1 text-xs font-medium text-ink-secondary', className)}>{children}</span>
}
