import { BadgeCheck, History, Search, ShieldAlert, ShieldX } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Button, Card, Container, Heading, Input, Money, Section, Skeleton, StatusBadge, Text } from '@/components/ui'
import { useVerify } from '@/hooks/queries'
import { cn } from '@/lib/cn'
import { formatDateTime } from '@/lib/format'

export function VerifyLookup({ className }: { className?: string }) {
  const [code, setCode] = useState('')
  const navigate = useNavigate()
  const submit = (e: FormEvent) => {
    e.preventDefault()
    const value = code.trim()
    if (value) navigate(`/verify/${encodeURIComponent(value)}`)
  }
  return (
    <form onSubmit={submit} className={cn('flex gap-2', className)}>
      <Input aria-label="Verification code" placeholder="NSV-9A84-F2E0-1C77" value={code} onChange={(e) => setCode(e.target.value)} prefixIcon={<Search />} wrapperClassName="flex-1" className="h-12 font-mono uppercase" />
      <Button type="submit" size="lg">Verify</Button>
    </form>
  )
}

const TITLES = { RECEIPT: 'Receipt', INVOICE: 'Invoice', CREDIT_NOTE: 'Credit note' } as const

export default function Verify() {
  const { code } = useParams()
  const { data, isLoading, isError } = useVerify(code)

  return (
    <Section className="min-h-[70vh]">
      <Container size="sm">
        <div className="text-center">
          <Text variant="eyebrow" tone="brand">Slip verification</Text>
          <Heading level={1} className="mt-3">Is this slip genuine?</Heading>
          <Text variant="lead" className="mt-3">Scan the QR on a NetSlip receipt, or type its NSV code.</Text>
        </div>
        <VerifyLookup className="mx-auto mt-8 max-w-md" />

        {code && (
          <div className="mt-10">
            {isLoading && <Skeleton className="h-72 rounded-2xl" />}
            {isError && (
              <Card variant="elevated" padding="lg" className="rounded-2xl text-center">
                <ShieldX className="mx-auto size-10 text-rose-600" />
                <h2 className="mt-3 text-lg font-bold">No slip matches this code</h2>
                <Text className="mt-1">Check the code for typos. If it came from a receipt, treat that receipt with caution.</Text>
              </Card>
            )}
            {data && (
              <Card variant="elevated" padding="none" className="overflow-hidden rounded-2xl animate-slip-in">
                <div className={cn('flex items-center gap-3 px-6 py-5', !data.authentic ? 'bg-rose-600 text-white' : data.superseded ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white')}>
                  {!data.authentic ? <ShieldAlert className="size-7" /> : data.superseded ? <History className="size-7" /> : <BadgeCheck className="size-7" />}
                  <div>
                    <p className="text-lg font-bold">{!data.authentic ? 'Tamper alert' : data.superseded ? 'Older version of this slip' : 'Verified authentic'}</p>
                    <p className="text-sm text-white/85">
                      {!data.authentic
                        ? 'This record no longer matches its signature. Contact the merchant.'
                        : data.superseded
                          ? `This slip was revised. The current version is revision ${data.current_revision}.`
                          : 'Issued by this merchant and unchanged since.'}
                    </p>
                  </div>
                </div>
                <div className="px-6 py-6">
                  <div className="flex items-center gap-3">
                    {data.merchant.logo_url ? <img src={data.merchant.logo_url} alt="" className="size-10 rounded-lg object-contain" /> : <span className="inline-flex size-10 items-center justify-center rounded-lg font-bold text-white" style={{ background: data.merchant.primary_color }}>{data.merchant.name[0]}</span>}
                    <div>
                      <p className="font-bold text-ink-primary">{data.merchant.name}</p>
                      <p className="text-xs text-ink-muted">On NetSlip since {data.merchant.verified_since}</p>
                    </div>
                  </div>
                  <dl className="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div><dt className="text-ink-muted">{TITLES[data.document.doc_type]}</dt><dd className="tabular font-semibold">{data.document.doc_number}</dd></div>
                    <div><dt className="text-ink-muted">Status</dt><dd className="mt-0.5"><StatusBadge status={data.document.status} size="sm" /></dd></div>
                    <div><dt className="text-ink-muted">Customer</dt><dd className="font-semibold">{data.document.customer_display}</dd></div>
                    <div><dt className="text-ink-muted">Issued</dt><dd className="font-semibold">{formatDateTime(data.document.issued_at)}</dd></div>
                    <div><dt className="text-ink-muted">Total</dt><dd><Money amountMinor={data.document.total_minor} currency={data.document.currency} className="font-bold" /></dd></div>
                    <div><dt className="text-ink-muted">Balance</dt><dd><Money amountMinor={data.document.balance_due_minor} currency={data.document.currency} className="font-semibold" /></dd></div>
                  </dl>
                  <div className="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-border-subtle pt-4 text-xs text-ink-muted">
                    <span className="tabular font-semibold text-ink-secondary">{data.document.verification_code}</span>
                    <StatusBadge status={data.document.is_mutable_record ? 'MUTABLE_WITH_AUDIT' : 'IMMUTABLE'} size="sm" showIcon />
                  </div>
                </div>
              </Card>
            )}
          </div>
        )}
      </Container>
    </Section>
  )
}
