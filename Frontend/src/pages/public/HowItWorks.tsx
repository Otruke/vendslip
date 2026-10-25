import { ArrowRight, CheckCheck, FileText, MessageSquareText, ShieldCheck } from 'lucide-react'
import { ChannelStrip } from '@/components/slip/ChannelStrip'
import { PaperSlipPreview } from '@/components/slip/PaperSlipPreview'
import { SAMPLE_SLIP } from '@/components/slip/sample'
import { Button, Card, Container, Heading, Section, StatusBadge, Text } from '@/components/ui'

function Bubble({ children, me = true }: { children: React.ReactNode; me?: boolean }) {
  return <div className={me ? 'ml-auto max-w-[85%] rounded-xl rounded-br-sm bg-[#dcf8c6] px-3 py-1.5 font-mono text-[12px]' : 'max-w-[90%] rounded-xl rounded-bl-sm bg-white px-3 py-2 text-[13px] shadow-sm'}>{children}</div>
}

const STEPS = [
  {
    n: 1,
    icon: <MessageSquareText />,
    title: 'Type /slash in chat',
    caption: 'Send fields one by one or all at once. Nothing is sent back yet.',
    visual: (
      <div className="space-y-2 rounded-xl bg-[#f4f1ec] p-3">
        <Bubble>/name = Ada Obi</Bubble>
        <Bubble>/item = 2x Ankara Dress @ 18,500</Bubble>
        <Bubble>/address = Lekki Phase 1</Bubble>
        <p className="pt-1 text-center text-[11px] text-ink-muted">Buffered silently · ₦0 messaging cost</p>
      </div>
    ),
  },
  {
    n: 2,
    icon: <CheckCheck />,
    title: 'Confirm one summary',
    caption: 'One consolidated message with Confirm, Edit or Cancel. That’s the only reply.',
    visual: (
      <div className="space-y-2 rounded-xl bg-[#f4f1ec] p-3">
        <Bubble me={false}>
          <p className="font-semibold">🧾 Receipt for Ada Obi</p>
          <p className="tabular text-ink-secondary">2 × Ankara Dress · ₦39,775.00</p>
          <div className="mt-2 grid grid-cols-3 gap-1 text-[11px] font-semibold">
            <span className="rounded-md bg-netslip-primary py-1 text-center text-white">✅ Confirm</span>
            <span className="rounded-md bg-surface-subtle py-1 text-center">✏️ Edit</span>
            <span className="rounded-md bg-surface-subtle py-1 text-center">❌ Cancel</span>
          </div>
        </Bubble>
        <p className="pt-1 text-center text-[11px] text-ink-muted">Telegram groups: visible to the agent only</p>
      </div>
    ),
  },
  {
    n: 3,
    icon: <FileText />,
    title: 'Share a verified slip',
    caption: 'Branded PDF with QR proof and a pay link for invoices.',
    visual: <div className="mx-auto max-w-[260px]"><PaperSlipPreview slip={SAMPLE_SLIP} compact /></div>,
  },
]

export default function HowItWorks() {
  return (
    <>
      <Section className="pb-10 lg:pb-14">
        <Container className="text-center">
          <Text variant="eyebrow" tone="brand">How it works</Text>
          <Heading level={1} variant="display" className="mx-auto mt-3 max-w-3xl">Three steps. One message. Done.</Heading>
          <Text variant="lead" className="mx-auto mt-5 max-w-2xl">Sell where your customers already chat. NetSlip handles the paperwork and the proof.</Text>
          <ChannelStrip className="mt-8 justify-center" />
        </Container>
      </Section>

      <Section tone="canvas" className="pt-14 lg:pt-16">
        <Container className="grid gap-6 lg:grid-cols-3">
          {STEPS.map((s) => (
            <Card key={s.n} variant="elevated" padding="lg" className="flex flex-col rounded-2xl">
              <div className="flex items-center gap-3">
                <span className="tabular inline-flex size-9 items-center justify-center rounded-full bg-netslip-deep text-sm font-bold text-white">{s.n}</span>
                <span className="inline-flex size-9 items-center justify-center rounded-lg bg-netslip-tint text-netslip-deep [&>svg]:size-4">{s.icon}</span>
              </div>
              <h2 className="mt-5 text-xl font-bold text-ink-primary">{s.title}</h2>
              <Text className="mt-2">{s.caption}</Text>
              <div className="mt-6 flex-1">{s.visual}</div>
            </Card>
          ))}
        </Container>
      </Section>

      <Section>
        <Container size="lg" className="grid items-center gap-10 lg:grid-cols-2">
          <div>
            <Heading>Every field you can send</Heading>
            <Text variant="lead" className="mt-3">Short, forgiving syntax. “15k”, “₦15,000” and “15000” all work.</Text>
            <Button to="/register" className="mt-8" rightIcon={<ArrowRight className="size-4" />}>Try it free</Button>
          </div>
          <Card padding="none" className="overflow-hidden rounded-2xl">
            <table className="w-full text-sm">
              <tbody className="divide-y divide-border-subtle">
                {[
                  ['/name', 'Ada Obi'], ['/item', '2x Shoe @ 15000 | Belt @ 5k'], ['/amount', '30000 (instead of items)'],
                  ['/type', 'receipt or invoice'], ['/discount', '10% or 2000'], ['/shipping', '3500'], ['/vat', '7.5, off, 7.5 incl'],
                  ['/paid', 'full or 20000'], ['/due', '7d or 30/11/2026'], ['/phone · /address · /note', 'free text'],
                ].map(([k, v]) => (
                  <tr key={k}><td className="w-48 px-4 py-2.5 font-mono text-[13px] font-semibold text-netslip-deep">{k}</td><td className="px-4 py-2.5 text-ink-secondary">{v}</td></tr>
                ))}
              </tbody>
            </table>
          </Card>
        </Container>
      </Section>

      <Section tone="canvas">
        <Container size="md" className="text-center">
          <ShieldCheck className="mx-auto size-10 text-netslip-primary" />
          <Heading className="mt-4">Proof your customers can check.</Heading>
          <Text variant="lead" className="mx-auto mt-3 max-w-xl">Each slip carries an HMAC-SHA256 fingerprint. Any change after issue shows up instantly.</Text>
          <div className="mt-6 flex justify-center gap-2"><StatusBadge status="VERIFIED" showIcon /><StatusBadge status="IMMUTABLE" showIcon /></div>
          <Button to="/verify" variant="outline" className="mt-8">Verify a slip</Button>
        </Container>
      </Section>
    </>
  )
}
