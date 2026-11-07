import { ArrowRight } from 'lucide-react'
import type { CSSProperties } from 'react'
import { photos, product, slips } from '@/assets'
import { HeroBackdrop } from '@/components/media/HeroBackdrop'
import { Photo } from '@/components/media/Photo'
import { ChannelStrip } from '@/components/slip/ChannelStrip'
import { HeroDemo } from '@/components/slip/demo/HeroDemo'
import { Button, Card, Container, Heading, Section, StatusBadge, Tagline, Text, Words } from '@/components/ui'
import { PricingCards } from './PricingCards'

/** Receipt-tape facts: printed like the bottom of a slip, not a generic stats row. */
const TAPE = [
  ['Chat channels', '4'],
  ['Messages to confirm', '1'],
  ['Free slips every month', '15'],
  ['Credit expiry', 'Never'],
]

/** Silent buffering, shown with the real chat styling used in the demo. */
function BufferVisual() {
  return (
    <div className="space-y-1.5 rounded-xl bg-[#ece5dc] p-3 text-[11px]" data-stagger style={{ '--d': '120ms' } as CSSProperties}>
      {['/item = 2x Ankara @ 18,500', '/discount = 10%', '/name = Ada Obi'].map((l) => (
        <p key={l} className="ml-auto w-fit rounded-lg rounded-tr-sm bg-[#d8f6cb] px-2 py-1 font-mono shadow-[0_1px_0.5px_rgba(0,0,0,0.12)]" data-reveal="slide">{l}</p>
      ))}
      <p className="mx-auto w-fit rounded bg-white/80 px-2 py-0.5 text-[10px] text-[#4b5b60]" data-reveal="rise">No replies sent · ₦0 spent</p>
      <p className="w-fit rounded-lg rounded-tl-sm bg-white px-2 py-1 font-semibold shadow-[0_1px_0.5px_rgba(0,0,0,0.12)]" data-reveal="stamp">Receipt ready. Confirm?</p>
    </div>
  )
}

const FEATURES = [
  { title: 'Silent draft buffering', text: 'Partial orders wait quietly. You pay for one confirmation, not ten.', visual: <BufferVisual /> },
  {
    title: 'Instant branded PDF',
    text: 'Your logo, colours and bank details on every single slip.',
    visual: (
      <div className="relative h-[168px] overflow-hidden rounded-xl bg-white/10">
        <div className="absolute left-1/2 top-3 w-[78%] -translate-x-1/2" data-reveal="feed" style={{ '--d': '160ms' } as CSSProperties}>
          <Photo image={slips.receiptMinimal} alt="A VendSlip receipt in the Minimalist Social design" sizes="320px" className="vs-print w-full rounded-t-md shadow-[0_10px_24px_-10px_rgba(22,53,64,0.45)]" />
        </div>
      </div>
    ),
  },
  {
    title: 'Cryptographic QR proof',
    text: 'Customers scan once to confirm a slip is real and unchanged.',
    visual: (
      <div className="flex h-[168px] items-center justify-center rounded-xl bg-white/10">
        <span className="vs-scan block w-[70%] max-w-[240px] rounded-md bg-white p-1 shadow-sm" data-reveal="stamp" style={{ '--d': '160ms' } as CSSProperties}>
          <Photo image={product.qrProof} alt="QR code and VSV verification code printed on a VendSlip receipt" sizes="240px" className="w-full" />
        </span>
      </div>
    ),
  },
]

export default function Landing() {
  return (
    <>
      {/* ── Hero ─────────────────────────────────────────────────────────── */}
      <section className="relative isolate overflow-hidden">
        <HeroBackdrop />
        <Container className="grid items-center gap-12 pb-16 pt-10 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.15fr)] lg:pb-24 lg:pt-16">
          <div className="animate-fade-up">
            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-vendslip-primary">Receipts for chat commerce</p>
            <Heading level={1} variant="display" className="mt-4">Turn chat orders into verified receipts.</Heading>
            <Text variant="lead" className="mt-5 max-w-xl">
              Type <code className="rounded-sm border border-vendslip-primary/25 bg-white px-1.5 py-0.5 font-mono text-[0.9em] text-vendslip-deep">/slash</code> commands in WhatsApp or Telegram. Confirm once, and your customer gets a signed slip.
            </Text>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button to="/register" size="lg" rightIcon={<ArrowRight className="size-5" />}>Start free</Button>
              <Button to="/how-it-works" size="lg" variant="outline" className="bg-white/80">How it works</Button>
            </div>
            <ChannelStrip className="mt-9" />
          </div>
          <div id="demo" className="scroll-mt-24">
            <HeroDemo />
          </div>
        </Container>
      </section>

      {/* ── Receipt tape ─────────────────────────────────────────────────── */}
      <section className="border-y border-dashed border-slate-300 bg-white">
        <Container className="grid gap-x-10 gap-y-3 py-7 font-mono text-sm sm:grid-cols-2 lg:grid-cols-4" data-stagger>
          {TAPE.map(([label, value]) => (
            <p key={label} className="vs-tape flex cursor-default items-baseline gap-2 text-ink-secondary" data-reveal="rise">
              <span className="shrink-0">{label}</span>
              <span className="vs-tape__lead min-w-4 flex-1 border-b border-dotted border-slate-300" aria-hidden />
              <span className="vs-tape__val shrink-0 font-bold text-vendslip-deep" data-count={/^\d+$/.test(value ?? '') ? '' : undefined}>{value}</span>
            </p>
          ))}
        </Container>
      </section>

      {/* ── Why VendSlip ──────────────────────────────────────────────────── */}
      <Section>
        <Container>
          <div className="max-w-2xl" data-stagger>
            <Text variant="eyebrow" tone="brand" data-reveal="rise">Why VendSlip</Text>
            <Heading className="mt-3" data-reveal="type"><Words>Built for sellers who live in chat.</Words></Heading>
            <Text variant="lead" className="mt-4" data-reveal="rise">Less typing and fewer disputes, with receipts your customers can check for themselves in seconds.</Text>
          </div>
          <div className="mt-12 grid gap-5 md:grid-cols-3" data-stagger>
            {FEATURES.map((f, i) => (
              <div key={f.title} className="flex" data-reveal="feed">
                <Card variant={(['obsidian', 'deep', 'teal'] as const)[i % 3]} padding="none" className="vs-slip min-w-0 flex-1 overflow-hidden">
                  <div className="p-3 pb-0">{f.visual}</div>
                  <div className="p-5">
                    <h3 className="text-lg font-bold text-white">{f.title}</h3>
                    <p className="mt-1.5 text-sm leading-relaxed text-white/80">{f.text}</p>
                  </div>
                </Card>
              </div>
            ))}
          </div>
        </Container>
      </Section>

      {/* ── Brand in the real world ──────────────────────────────────────── */}
      <section className="relative isolate overflow-hidden">
        <Photo image={photos.desk} alt="A VendSlip-branded notebook, mug and card holder on a seller's desk" sizes="100vw" className="absolute inset-x-0 -top-[12%] -z-10 h-[124%] w-full object-cover" data-parallax="0.14" />
        <div className="absolute inset-0 -z-10 bg-vendslip-obsidian/75" aria-hidden />
        <Container className="py-24 lg:py-32">
          <div className="max-w-lg text-white" data-stagger>
            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#9fd0da]" data-reveal="rise">Made for real counters</p>
            <h2 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl" data-reveal="type"><Words>Your brand on every slip.</Words></h2>
            <p className="mt-4 text-lg leading-relaxed text-[#d7e8ec]" data-reveal="rise">From Balogun Market to boutique studios, every receipt carries your name, colours and account details.</p>
            <div className="mt-8" data-reveal="rise"><Button to="/register" variant="white" rightIcon={<ArrowRight className="size-4" />}>Set up your brand</Button></div>
          </div>
        </Container>
      </section>

      {/* ── Ledger ───────────────────────────────────────────────────────── */}
      <Section className="bg-vendslip-obsidian text-white">
        <Container className="grid items-center gap-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
          <div data-stagger>
            <Text variant="eyebrow" className="text-[#9fd0da]" data-reveal="rise">Your ledger, your rules</Text>
            <Heading className="mt-3 text-white" data-reveal="type"><Words>Locked records. Or audited edits.</Words></Heading>
            <Text variant="lead" tone="inverse" className="mt-4" data-reveal="rise">Keep slips locked with credit notes, or allow edits that leave a full forensic trail.</Text>
            <dl className="mt-8 divide-y divide-dashed divide-white/20 border-y border-dashed border-white/20">
              {[
                ['Every edit', 'Stores who, when, why and the exact before and after.'],
                ['One ledger', 'Invoices, part-payments, auto-receipts and credit notes together.'],
                ['Paid online', 'Customers settle by card, transfer, USSD or crypto.'],
              ].map(([term, text]) => (
                <div key={term} className="vs-row grid gap-1 py-4 [--row:#9fd0da] sm:grid-cols-[130px_1fr]" data-reveal="rise">
                  <dt className="font-mono text-xs font-bold uppercase tracking-wider text-[#9fd0da] sm:pt-0.5">{term}</dt>
                  <dd className="text-base text-white">{text}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div className="relative pb-16 sm:pb-20">
            {/* Real dashboard, in a browser frame */}
            <div className="overflow-hidden rounded-xl border border-white/15 bg-white shadow-[0_30px_70px_-30px_rgba(0,0,0,0.6)]" data-reveal="feed">
              <div className="flex items-center gap-3 border-b border-border-subtle bg-surface-canvas px-3 py-2">
                <img src="/assets/brand/vendslip-icon.png" alt="" className="h-3.5 w-auto opacity-70" />
                <span className="flex-1 truncate rounded bg-white px-2 py-0.5 font-mono text-[10px] text-ink-muted">app.vendslip.com/dashboard/documents</span>
              </div>
              <Photo image={product.ledger} alt="The VendSlip receipts and invoices ledger with paid, part-paid and overdue slips" sizes="(min-width: 1024px) 620px, 100vw" />
            </div>
            {/* Revision trail, floating over the screenshot */}
            <Card variant="elevated" padding="none" className="absolute -bottom-2 right-0 w-[min(360px,92%)] overflow-hidden sm:-right-4" data-reveal="slide" style={{ '--d': '450ms' } as CSSProperties}>
              <div className="flex items-center justify-between gap-2 border-b border-border-subtle px-4 py-2.5">
                <p className="text-xs font-semibold">INV-2026-0001 · history</p>
                <StatusBadge status="MUTABLE_WITH_AUDIT" size="sm" />
              </div>
              <ul className="divide-y divide-border-subtle text-xs">
                {[
                  ['Rev 2', 'Tobi (Sales) · “Customer asked for faster delivery”', 'shipping ₦3,500 → ₦5,000'],
                  ['Payment', 'Amina (Owner) · transfer MONIE-88421', 'balance ₦116,375 → ₦96,375'],
                ].map(([a, b, c]) => (
                  <li key={a} className="grid grid-cols-[64px_1fr] gap-2 px-4 py-2.5">
                    <p className="font-semibold text-ink-primary">{a}</p>
                    <div><p className="text-ink-secondary">{b}</p><p className="tabular mt-0.5 text-[11px] text-ink-muted">{c}</p></div>
                  </li>
                ))}
              </ul>
            </Card>
          </div>
        </Container>
      </Section>

      {/* ── Pricing ──────────────────────────────────────────────────────── */}
      <Section>
        <Container>
          <div className="mx-auto max-w-2xl text-center" data-stagger>
            <Text variant="eyebrow" tone="brand" data-reveal="rise">Pricing</Text>
            <Heading className="mt-3" data-reveal="type"><Words>Start free. Pay as you grow.</Words></Heading>
            <Text variant="lead" className="mt-4" data-reveal="rise">No card needed to start. Credit packs never expire and remove the watermark from paid slips.</Text>
          </div>
          <PricingCards className="mt-12" />
        </Container>
      </Section>

      {/* ── Closing call to action ───────────────────────────────────────── */}
      <section className="px-4 pb-20 sm:px-6 lg:px-8">
        <div className="relative isolate mx-auto max-w-7xl overflow-hidden rounded-3xl px-6 py-16 text-center sm:px-12" data-reveal="feed" data-stagger>
          <Photo image={photos.wallDark} alt="" sizes="(min-width: 1280px) 1280px, 100vw" className="absolute inset-x-0 -top-[15%] -z-10 h-[130%] w-full object-cover" data-parallax="0.12" />
          <div className="absolute inset-0 -z-10 bg-vendslip-obsidian/80" aria-hidden />
          <div data-reveal="stamp" style={{ '--d': '250ms' } as CSSProperties}><Tagline className="block text-4xl text-white sm:text-5xl" /></div>
          <Text tone="inverse" variant="lead" className="mx-auto mt-4 max-w-xl" data-reveal="rise" style={{ '--d': '400ms' } as CSSProperties}>Set up in two minutes. Your first fifteen slips every month are on us.</Text>
          <div className="mt-8 flex flex-wrap justify-center gap-3" data-reveal="rise" style={{ '--d': '520ms' } as CSSProperties}>
            <Button to="/register" size="lg" variant="white" rightIcon={<ArrowRight className="size-5" />}>Create free account</Button>
            <Button to="/how-it-works" size="lg" variant="deep" className="ring-1 ring-white/25">See how it works</Button>
          </div>
        </div>
      </section>
    </>
  )
}
