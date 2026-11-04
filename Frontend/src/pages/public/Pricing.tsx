import { slips } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Container, Heading, Section, Text, Words } from '@/components/ui'
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
          <div className="mx-auto max-w-2xl text-center" data-stagger>
            <Text variant="eyebrow" tone="brand" data-reveal="rise">Pricing</Text>
            <Heading level={1} variant="display" className="mt-3" data-reveal="type"><Words>Simple plans. No surprises.</Words></Heading>
            <Text variant="lead" className="mt-5" data-reveal="rise">Start free, upgrade when it pays for itself. Cancel any time.</Text>
          </div>
          <PricingCards className="mt-12" />
          <div className="mt-10 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm" data-stagger>
            <span className="inline-block text-[11px] font-bold uppercase tracking-[0.16em] text-ink-muted" data-reveal="rise">Customers pay by</span>
            {['Card', 'Bank transfer', 'USSD', 'USDT · USDC · BTC · TON'].map((m) => (
              <span key={m} className="group inline-flex cursor-default items-center gap-2 font-semibold text-ink-primary" data-reveal="rise"><span className="size-1.5 rotate-45 bg-vendslip-primary transition-[rotate,scale] duration-500 group-hover:rotate-[225deg] group-hover:scale-150" aria-hidden />{m}</span>
            ))}
            <span className="inline-block text-ink-muted" data-reveal="rise">via Flutterwave & NOWPayments</span>
          </div>
        </Container>
      </Section>
      <Section tone="canvas">
        <Container className="grid items-start gap-12 lg:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)]">
          <div data-stagger>
            <Heading data-reveal="type"><Words>Questions, answered</Words></Heading>
            <Text variant="lead" className="mt-3" data-reveal="rise">Every paid slip looks like this one: your brand, no watermark, QR proof included.</Text>
            <div className="mt-8" data-reveal="feed">
              <Photo image={slips.invoice} alt="A VendSlip invoice without a watermark" sizes="(min-width: 1024px) 420px, 90vw" className="rounded-md shadow-[0_24px_50px_-24px_rgba(22,53,64,0.5)]" />
            </div>
          </div>
          <dl className="grid gap-4 sm:grid-cols-2" data-stagger>
            {FAQ.map(([q, a]) => (
              <div key={q} className="flex" data-reveal="rise">
              <div className="vs-slip min-w-0 flex-1 rounded-xl bg-vendslip-mist p-5">
                <dt className="font-semibold text-ink-primary">{q}</dt>
                <dd className="mt-2 text-sm text-ink-secondary">{a}</dd>
              </div>
              </div>
            ))}
          </dl>
        </Container>
      </Section>
    </>
  )
}
