import { Copy, Download, ExternalLink, MessageCircle, Send } from 'lucide-react'
import { Alert, Button, InfoTip, Skeleton } from '@/components/ui'
import { useShareLinks } from '@/hooks/queries'
import { toast } from '@/stores/ui'

export function ShareSheet({ documentId }: { documentId: string }) {
  const { data, isLoading, isError } = useShareLinks(documentId, true)
  if (isLoading) return <Skeleton className="h-28" />
  if (isError || !data) return <Alert tone="warning">Couldn’t prepare share links. You can still download the PDF.</Alert>

  const copy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text)
      toast(`${label} copied`)
    } catch {
      toast('Copy failed — select and copy manually', 'error')
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 rounded-lg border border-border-subtle bg-surface-canvas py-1.5 pl-3 pr-1.5">
        <span className="min-w-0 flex-1 truncate font-mono text-sm text-ink-primary" title={data.share_url}>{data.share_url.replace(/^https?:\/\//, '')}</span>
        <Button size="xs" variant="outline" leftIcon={<Copy className="size-3.5" />} onClick={() => copy(data.share_url, 'Link')}>Copy link</Button>
        <InfoTip label="About this link">The one link to send your customer. It opens the slip, lets them download the PDF and, for invoices, pay.</InfoTip>
      </div>
      <div className="grid gap-2 sm:grid-cols-2">
        <Button href={data.whatsapp_url} target="_blank" variant="success" leftIcon={<MessageCircle className="size-4" />}>Send on WhatsApp</Button>
        <Button href={data.telegram_url} target="_blank" variant="outline" leftIcon={<Send className="size-4" />}>Share to Telegram</Button>
        <Button href={`/api/v1/documents/${documentId}/pdf?download=1`} variant="outline" leftIcon={<Download className="size-4" />}>Download PDF</Button>
        <Button variant="outline" leftIcon={<Copy className="size-4" />} onClick={() => copy(data.message, 'Message')}>Copy message</Button>
        <Button href={data.share_url} target="_blank" variant="ghost" leftIcon={<ExternalLink className="size-4" />}>Open customer page</Button>
        <Button href={data.verify_url} target="_blank" variant="ghost" leftIcon={<ExternalLink className="size-4" />}>Open verification page</Button>
      </div>
    </div>
  )
}
