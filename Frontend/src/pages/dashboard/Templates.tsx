import { Check, Lock, Palette } from 'lucide-react'
import { useState } from 'react'
import { Button, Card, PageHeader, SegmentedTabs, Skeleton, StatusBadge } from '@/components/ui'
import { apiPut, useInvalidate, useRefreshSession, useTemplates } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { useCan } from '@/stores/auth'
import { toast } from '@/stores/ui'

export default function Templates() {
  const { data, isLoading } = useTemplates()
  const [type, setType] = useState<'RECEIPT' | 'INVOICE'>('RECEIPT')
  const canManage = useCan('settings.manage')
  const invalidate = useInvalidate()
  const refreshSession = useRefreshSession()
  const [saving, setSaving] = useState<number | null>(null)
  const currentDefault = type === 'INVOICE' ? data?.defaults.invoice : data?.defaults.receipt

  const makeDefault = async (id: number) => {
    setSaving(id)
    try {
      await apiPut('/settings/templates', type === 'INVOICE' ? { default_invoice_template_id: id } : { default_receipt_template_id: id })
      await Promise.all([invalidate('templates'), refreshSession()])
      toast(`Default ${type === 'INVOICE' ? 'invoice' : 'receipt'} template updated`)
    } catch (e) {
      toast(errorMessage(e), 'error')
    } finally {
      setSaving(null)
    }
  }

  const templates = data?.data.filter((t) => (type === 'INVOICE' ? t.supports_invoice : t.supports_receipt)) ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Template gallery"
        subtitle="Real PDF previews with your logo, colours and bank details."
        actions={<>
          <SegmentedTabs value={type} onChange={setType} options={[{ value: 'RECEIPT', label: 'Receipt' }, { value: 'INVOICE', label: 'Invoice' }]} />
          <Button to="/dashboard/settings?tab=branding" variant="outline" leftIcon={<Palette className="size-4" />}>Brand colours</Button>
        </>}
      />
      <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        {isLoading && [0, 1, 2].map((i) => <Skeleton key={i} className="h-[460px] rounded-xl" />)}
        {templates.map((t) => {
          const isDefault = (currentDefault ?? data?.data.find((x) => x.is_default)?.id) === t.id
          return (
            <Card key={t.id} variant="interactive" padding="none" className="overflow-hidden">
              <div className="relative aspect-[3/4] bg-surface-subtle">
                {t.is_locked ? (
                  <div className="flex h-full flex-col items-center justify-center gap-2 text-ink-muted"><Lock className="size-6" /><p className="text-sm">Available on {t.min_plan_tier.replace('_', ' ').toLowerCase()}</p></div>
                ) : (
                  <iframe title={`${t.name} preview`} src={`/api/v1/templates/${t.id}/preview?type=${type}#toolbar=0&navpanes=0&view=FitH`} className="h-full w-full" loading="lazy" />
                )}
                {isDefault && <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-netslip-deep px-2.5 py-1 text-xs font-semibold text-white shadow"><Check className="size-3.5" />Default</span>}
              </div>
              <div className="flex items-start justify-between gap-3 p-4">
                <div className="min-w-0">
                  <p className="font-semibold text-ink-primary">{t.name}</p>
                  <p className="mt-0.5 line-clamp-2 text-sm text-ink-secondary">{t.description}</p>
                  <div className="mt-2 flex gap-1.5">{t.engine === 'html' && <StatusBadge status="PRO" label="Custom" size="sm" />}{t.min_plan_tier !== 'FREE' && <StatusBadge status={t.min_plan_tier} size="sm" />}</div>
                </div>
                {canManage && !t.is_locked && !isDefault && <Button size="sm" variant="secondary" isLoading={saving === t.id} onClick={() => makeDefault(t.id)}>Use</Button>}
                {t.is_locked && <Button size="sm" to="/dashboard/billing" variant="outline">Upgrade</Button>}
              </div>
            </Card>
          )
        })}
      </div>
    </div>
  )
}
