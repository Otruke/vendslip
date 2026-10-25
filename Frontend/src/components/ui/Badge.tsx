import { BadgeCheck, CircleCheck, Clock, FilePen, Lock, TriangleAlert } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

type Tone = 'emerald' | 'amber' | 'rose' | 'slate' | 'teal' | 'sky' | 'pink' | 'blue' | 'brand' | 'deep'
const tones: Record<Tone, { pill: string; dot: string }> = {
  emerald: { pill: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
  amber: { pill: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
  rose: { pill: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' },
  slate: { pill: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' },
  teal: { pill: 'bg-teal-50 text-netslip-deep border-teal-200', dot: 'bg-netslip-deep' },
  sky: { pill: 'bg-sky-50 text-sky-700 border-sky-200', dot: 'bg-sky-500' },
  pink: { pill: 'bg-pink-50 text-pink-700 border-pink-200', dot: 'bg-pink-500' },
  blue: { pill: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-600' },
  brand: { pill: 'bg-netslip-tint text-netslip-deep border-[#cfe3e8]', dot: 'bg-netslip-primary' },
  deep: { pill: 'bg-netslip-deep text-white border-netslip-deep', dot: 'bg-white' },
}

const STATUS: Record<string, { tone: Tone; label: string; icon?: ReactNode; pulse?: boolean }> = {
  PAID: { tone: 'emerald', label: 'Paid', icon: <CircleCheck /> },
  PARTIAL: { tone: 'amber', label: 'Part paid', icon: <Clock /> },
  UNPAID: { tone: 'rose', label: 'Unpaid', icon: <Clock /> },
  OVERDUE: { tone: 'rose', label: 'Overdue', icon: <TriangleAlert />, pulse: true },
  DRAFT: { tone: 'slate', label: 'Draft' },
  VOID: { tone: 'slate', label: 'Void' },
  IMMUTABLE: { tone: 'teal', label: 'Immutable', icon: <Lock /> },
  MUTABLE_WITH_AUDIT: { tone: 'sky', label: 'Mutable · Audited', icon: <FilePen /> },
  MUTABLE_UNRESTRICTED: { tone: 'sky', label: 'Mutable', icon: <FilePen /> },
  VERIFIED: { tone: 'emerald', label: 'Verified authentic', icon: <BadgeCheck /> },
  SUCCESSFUL: { tone: 'emerald', label: 'Successful' },
  PENDING: { tone: 'amber', label: 'Pending', pulse: true },
  FAILED: { tone: 'rose', label: 'Failed' },
  CANCELLED: { tone: 'slate', label: 'Cancelled' },
  ACTIVE: { tone: 'emerald', label: 'Active' },
  SUSPENDED: { tone: 'rose', label: 'Suspended' },
  FREE: { tone: 'slate', label: 'Free' },
  PRO: { tone: 'brand', label: 'Pro' },
  BUSINESS_PLUS: { tone: 'deep', label: 'Business Plus' },
  WHATSAPP: { tone: 'emerald', label: 'WhatsApp' },
  TELEGRAM: { tone: 'sky', label: 'Telegram' },
  INSTAGRAM: { tone: 'pink', label: 'Instagram' },
  FACEBOOK: { tone: 'blue', label: 'Facebook' },
  WEB: { tone: 'slate', label: 'Web' },
  RECEIPT: { tone: 'brand', label: 'Receipt' },
  INVOICE: { tone: 'deep', label: 'Invoice' },
  CREDIT_NOTE: { tone: 'amber', label: 'Credit note' },
}

export function StatusBadge({ status, label, size = 'md', showDot = true, showIcon = false, className }: {
  status: string
  label?: ReactNode
  size?: 'sm' | 'md'
  showDot?: boolean
  showIcon?: boolean
  className?: string
}) {
  const def = STATUS[status] ?? { tone: 'slate' as Tone, label: status.replace(/_/g, ' ').toLowerCase().replace(/^\w/, (c) => c.toUpperCase()) }
  const tone = tones[def.tone]
  return (
    <span className={cn('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border font-semibold', tone.pill, size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs', className)}>
      {showIcon && def.icon ? <span className="[&>svg]:size-3.5">{def.icon}</span> : showDot ? <span className={cn('size-1.5 rounded-full', tone.dot, def.pulse && 'animate-pulse')} /> : null}
      {label ?? def.label}
    </span>
  )
}

export function Pill({ children, className }: { children: ReactNode; className?: string }) {
  return <span className={cn('inline-flex items-center gap-1.5 rounded-full border border-border-subtle bg-white px-3 py-1 text-xs font-medium text-ink-secondary', className)}>{children}</span>
}
