import { Bitcoin, CreditCard, Landmark, Smartphone } from 'lucide-react'
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
          <div className="mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-ink-secondary">
            <span className="inline-flex items-center gap-2"><CreditCard className="size-4" />Cards</span>
            <span className="inline-flex items-center gap-2"><Landmark className="size-4" />Bank transfer</span>
            <span className="inline-flex items-center gap-2"><Smartphone className="size-4" />USSD</span>
            <span className="inline-flex items-center gap-2"><Bitcoin className="size-4" />USDT, USDC, BTC, TON</span>
            <span className="text-ink-muted">via Flutterwave & NOWPayments</span>
          </div>
        </Container>
      </Section>
      <Section tone="canvas">
        <Container size="md">
          <Heading className="text-center">Questions, answered</Heading>
          <dl className="mt-10 grid gap-6 sm:grid-cols-2">
            {FAQ.map(([q, a]) => (
              <div key={q} className="rounded-2xl border border-border-subtle bg-white p-6">
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
