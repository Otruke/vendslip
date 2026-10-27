import { slips } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Container, Heading, Section, Text } from '@/components/ui'
import { PricingCards } from './PricingCards'

const FAQ = [
  ['What counts as a slip?', 'Every receipt or invoice you issue. Payment receipts and credit notes are free.'],
  ['Do credits expire?', 'Never. Unused credits stay on your account for as long as you need them.'],
  ['What happens after 15 free slips?', 'Buy a credit pack or upgrade. Your records stay safe either way.'],
  ['Can customers pay online?', 'Yes on Business Plus: card, transfer, USSD via Flutterwave, or crypto.'],
]

export default function Pricing() {
  return (
    <>
      <Section className="pb-12">
        <Container>
          <div className="mx-auto max-w-2xl text-center">
            <Text variant="eyebrow" tone="brand">Pricing</Text>
            <Heading level={1} variant="display" className="mt-3">Simple plans. No surprises.</Heading>
            <Text variant="lead" className="mt-5">Start free, upgrade when it pays for itself. Cancel any time.</Text>
          </div>
          <PricingCards className="mt-12" />
          <div className="mt-10 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm">
            <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-ink-muted">Customers pay by</span>
            {['Card', 'Bank transfer', 'USSD', 'USDT · USDC · BTC · TON'].map((m) => (
              <span key={m} className="inline-flex items-center gap-2 font-semibold text-ink-primary"><span className="size-1.5 rotate-45 bg-netslip-primary" aria-hidden />{m}</span>
            ))}
            <span className="text-ink-muted">via Flutterwave & NOWPayments</span>
          </div>
        </Container>
      </Section>
      <Section tone="canvas">
        <Container className="grid items-start gap-12 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)]">
          <div>
            <Heading>Questions, answered</Heading>
            <Text variant="lead" className="mt-3">Every paid slip looks like this one: your brand, no watermark, QR proof included.</Text>
            <Photo image={slips.invoice} alt="A NetSlip invoice without a watermark" sizes="(min-width: 1024px) 420px, 90vw" className="mt-8 rounded-md shadow-[0_24px_50px_-24px_rgba(22,53,64,0.5)]" />
          </div>
          <dl className="grid gap-4 sm:grid-cols-2">
            {FAQ.map(([q, a]) => (
              <div key={q} className="rounded-xl border border-border-subtle bg-white p-5">
                <dt className="font-semibold text-ink-primary">{q}</dt>
                <dd className="mt-2 text-sm text-ink-secondary">{a}</dd>
              </div>
            ))}
          </dl>
        </Container>
      </Section>
    </>
  )
}
