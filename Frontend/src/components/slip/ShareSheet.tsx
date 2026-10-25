import { Copy, Download, ExternalLink, MessageCircle, Send } from 'lucide-react'
import { Alert, Button, Skeleton } from '@/components/ui'
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
    <div className="grid gap-2 sm:grid-cols-2">
      <Button href={data.whatsapp_url} target="_blank" variant="success" leftIcon={<MessageCircle className="size-4" />}>Send on WhatsApp</Button>
      <Button href={data.telegram_url} target="_blank" variant="outline" leftIcon={<Send className="size-4" />}>Share to Telegram</Button>
      <Button href={`/api/v1/documents/${documentId}/pdf?download=1`} variant="outline" leftIcon={<Download className="size-4" />}>Download PDF</Button>
      <Button variant="outline" leftIcon={<Copy className="size-4" />} onClick={() => copy(data.message, 'Message')}>Copy message</Button>
      {data.pay_url && <Button variant="secondary" leftIcon={<Copy className="size-4" />} onClick={() => copy(data.pay_url ?? '', 'Pay link')}>Copy pay link</Button>}
      <Button href={data.verify_url} target="_blank" variant="ghost" leftIcon={<ExternalLink className="size-4" />}>Open verification page</Button>
    </div>
  )
}
