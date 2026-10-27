import { BadgeCheck } from 'lucide-react'
import { StatusBadge } from '@/components/ui'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/money'
import { QrStamp } from './QrStamp'
import type { SlipView } from './types'

const TITLES = { RECEIPT: 'Receipt', INVOICE: 'Invoice', CREDIT_NOTE: 'Credit note' } as const

/** Live, tactile paper-slip preview mirroring the DOMPDF templates. */
export function PaperSlipPreview({ slip, compact = false, className }: { slip: SlipView; compact?: boolean; className?: string }) {
  const m = (minor: number) => formatMoney(minor, slip.currency)
  const minimal = slip.variant === 'minimal'

  return (
    <div className={cn('relative', className)}>
      <div className="serrated relative overflow-hidden rounded-t-xl bg-white pb-6 shadow-[0_24px_60px_-28px_rgba(15,23,42,0.55)]">
        {slip.watermark && (
          <div className="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden>
            <span className="-rotate-[24deg] whitespace-nowrap text-3xl font-extrabold tracking-wider text-netslip-primary/[0.07]">POWERED BY NETSLIP</span>
          </div>
        )}
        {slip.status === 'VOID' && (
          <div className="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden>
            <span className="-rotate-[24deg] text-6xl font-black tracking-[0.3em] text-tone-open/20">VOID</span>
          </div>
        )}

        {/* Header */}
        {minimal ? (
          <div className={cn('px-6 pt-6 text-center', compact && 'px-5 pt-5')}>
            {slip.logoUrl && <img src={slip.logoUrl} alt="" className="mx-auto mb-2 h-9 w-auto" />}
            <p className="text-base font-extrabold" style={{ color: slip.secondary }}>{slip.businessName || 'Your business'}</p>
            {slip.businessMeta && <p className="text-[11px] text-ink-muted">{slip.businessMeta}</p>}
            <p className="mt-2 text-[10px] font-extrabold uppercase tracking-[0.3em]" style={{ color: slip.primary }}>{TITLES[slip.docType]} · <span className="tabular">{slip.docNumber}</span></p>
            <div className="mt-2"><StatusBadge status={slip.status} size="sm" /></div>
          </div>
        ) : (
          <div className={cn('px-6 py-5 text-white', compact && 'px-5 py-4')} style={{ background: slip.secondary }}>
            <div className="flex items-start justify-between gap-3">
              <div className="flex min-w-0 items-center gap-2.5">
                {slip.logoUrl && <img src={slip.logoUrl} alt="" className="h-9 w-9 shrink-0 rounded-md bg-white object-contain p-0.5" />}
                <div className="min-w-0">
                  <p className="truncate text-base font-extrabold leading-tight">{slip.businessName || 'Your business'}</p>
                  {slip.businessMeta && <p className="truncate text-[11px] text-white/70">{slip.businessMeta}</p>}
                </div>
              </div>
              <div className="shrink-0 text-right">
                <p className="text-[10px] font-bold uppercase tracking-[0.25em] text-white/70">{TITLES[slip.docType]}</p>
                <p className="tabular text-sm font-black tracking-wide">{slip.docNumber}</p>
              </div>
            </div>
          </div>
        )}
        {!minimal && <div className="h-1" style={{ background: slip.primary }} />}

        <div className={cn('px-6', compact && 'px-5')}>
          {/* Meta */}
          <div className={cn('flex items-start justify-between gap-4', minimal ? 'mt-4 border-t border-dashed border-slate-300 pt-4' : 'mt-4')}>
            <div className="min-w-0">
              <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-ink-muted">{slip.docType === 'INVOICE' ? 'Billed to' : 'For'}</p>
              <p className="truncate text-sm font-bold text-ink-primary">{slip.customerName || 'Customer name'}</p>
              {slip.customerPhone && <p className="tabular text-[11px] text-ink-secondary">{slip.customerPhone}</p>}
              {!compact && slip.customerAddress && <p className="text-[11px] text-ink-secondary">{slip.customerAddress}</p>}
            </div>
            <div className="shrink-0 text-right">
              {!minimal && <StatusBadge status={slip.status} size="sm" />}
              <p className="tabular mt-1 text-[11px] text-ink-secondary">{slip.issuedAt}</p>
              {slip.dueDate && <p className="tabular text-[11px] font-semibold text-ink-primary">Due {slip.dueDate}</p>}
            </div>
          </div>

          {/* Items */}
          <div className="mt-4">
            <div className={cn('grid grid-cols-[1fr_auto] gap-2 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider', minimal ? 'border-b border-border-subtle text-ink-muted' : 'rounded-md text-white')} style={minimal ? undefined : { background: slip.primary }}>
              <span>Item</span><span>Amount</span>
            </div>
            {slip.lines.length === 0 ? (
              <p className="px-2 py-4 text-center text-xs text-ink-muted">Items appear here as you type</p>
            ) : (
              <ul className="divide-y divide-border-subtle">
                {slip.lines.slice(0, compact ? 3 : 50).map((line, i) => (
                  <li key={i} className="grid grid-cols-[1fr_auto] gap-2 px-2 py-2 animate-fade-up">
                    <div className="min-w-0">
                      <p className="truncate text-xs font-semibold text-ink-primary">{line.description || 'Item'}</p>
                      <p className="tabular text-[11px] text-ink-muted">{line.quantity} × {m(line.unit_minor)}</p>
                    </div>
                    <p className="tabular text-xs font-bold text-ink-primary">{m(line.total_minor)}</p>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Totals */}
          <dl className="tabular mt-2 space-y-1 border-t border-border-subtle px-2 pt-2 text-xs">
            <div className="flex justify-between text-ink-secondary"><dt>Subtotal</dt><dd>{m(slip.subtotal)}</dd></div>
            {slip.discount > 0 && <div className="flex justify-between text-ink-secondary"><dt>Discount</dt><dd>-{m(slip.discount)}</dd></div>}
            {slip.tax > 0 && <div className="flex justify-between text-ink-secondary"><dt>{slip.taxLabel}</dt><dd>{m(slip.tax)}</dd></div>}
            {slip.shipping > 0 && <div className="flex justify-between text-ink-secondary"><dt>Shipping</dt><dd>{m(slip.shipping)}</dd></div>}
          </dl>
          <div className={cn('mt-2 flex items-center justify-between rounded-lg px-3 py-2.5', minimal ? 'border-t-2' : 'text-white')} style={minimal ? { borderColor: slip.primary, color: slip.secondary } : { background: slip.secondary }}>
            <span className="text-xs font-bold uppercase tracking-wider">Total</span>
            <span className="tabular text-lg font-black">{m(slip.total)}</span>
          </div>
          {slip.balance > 0 && (
            <dl className="tabular mt-2 space-y-1 px-2 text-xs">
              {slip.paid > 0 && <div className="flex justify-between text-tone-settled"><dt>Paid</dt><dd>-{m(slip.paid)}</dd></div>}
              <div className="flex justify-between font-bold text-tone-open"><dt>Balance due</dt><dd>{m(slip.balance)}</dd></div>
            </dl>
          )}

          {!compact && slip.bank && (
            <div className="mt-3 rounded-lg border border-border-subtle bg-surface-canvas px-3 py-2">
              <p className="text-[10px] font-bold uppercase tracking-wider text-ink-muted">Pay by transfer</p>
              <p className="tabular text-sm font-bold text-ink-primary">{slip.bank.number}</p>
              <p className="text-[11px] text-ink-secondary">{[slip.bank.name, slip.bank.accountName].filter(Boolean).join(' · ')}</p>
            </div>
          )}

          {/* Proof */}
          <div className="mt-4 flex items-center gap-3 border-t border-dashed border-slate-300 pt-4">
            <QrStamp seed={slip.verificationCode ?? slip.docNumber} size={compact ? 46 : 58} />
            <div className="min-w-0">
              <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-ink-muted"><BadgeCheck className="size-3 text-netslip-primary" /> Scan to verify</p>
              <p className="tabular truncate text-xs font-bold text-ink-primary">{slip.verificationCode ?? 'NSV-···· on issue'}</p>
              <p className="text-[10px] text-ink-muted">HMAC-SHA256 signed</p>
            </div>
          </div>
          <p className={cn('mt-3 text-center font-script', compact ? 'text-lg' : 'text-xl')} style={{ color: slip.secondary }}>{slip.footer || 'Thank you for your patronage!'}</p>
        </div>
      </div>
    </div>
  )
}
