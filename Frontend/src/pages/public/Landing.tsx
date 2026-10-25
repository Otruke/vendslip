import { ArrowRight, BadgeCheck, FileText, Layers, QrCode, Sparkles, Wallet, Zap } from 'lucide-react'
import { ChannelStrip } from '@/components/slip/ChannelStrip'
import { ChatToSlipDemo } from '@/components/slip/ChatToSlipDemo'
import { Button, Card, Container, Heading, Section, StatusBadge, Tagline, Text } from '@/components/ui'
import { PricingCards } from './PricingCards'

const FEATURES = [
  { icon: <Zap />, title: 'Zero-waste drafts', text: 'Partial orders wait silently. You pay for one confirmation, not ten.', badge: 'PAID', badgeLabel: '₦0 buffering' },
  { icon: <FileText />, title: 'Instant branded PDF', text: 'Your logo, colours and bank details on every slip.', badge: 'RECEIPT', badgeLabel: 'Classic & Social' },
  { icon: <QrCode />, title: 'Cryptographic QR proof', text: 'Customers scan to confirm a slip is real and untouched.', badge: 'VERIFIED', badgeLabel: 'HMAC-SHA256' },
]

const STATS = [
  ['4', 'chat channels'],
  ['1', 'message to confirm'],
  ['15', 'free slips monthly'],
  ['0', 'expiry on credits'],
]

export default function Landing() {
  return (
    <>
      <section className="relative overflow-hidden">
        <div className="absolute inset-x-0 top-0 -z-10 h-[620px] bg-[radial-gradient(60%_60%_at_80%_0%,#eaf2f4_0%,transparent_70%)]" aria-hidden />
        <Container className="grid items-center gap-12 py-14 lg:grid-cols-[1fr_1.1fr] lg:py-24">
          <div className="animate-fade-up">
            <p className="inline-flex items-center gap-2 rounded-full border border-[#cfe3e8] bg-white px-3 py-1 text-xs font-semibold text-netslip-deep">
              <Sparkles className="size-3.5" /> Receipts for chat commerce
            </p>
            <Heading level={1} variant="display" className="mt-5">Turn chat orders into verified receipts.</Heading>
            <Text variant="lead" className="mt-5 max-w-xl">
              Type <code className="rounded bg-netslip-tint px-1.5 py-0.5 font-mono text-[0.9em] text-netslip-deep">/slash</code> in WhatsApp or Telegram. Confirm once. Send a branded, tamper-proof slip.
            </Text>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button to="/register" size="lg" rightIcon={<ArrowRight className="size-5" />}>Start free — 15 slips/mo</Button>
              <Button href="#demo" size="lg" variant="outline">Try live demo</Button>
            </div>
            <ChannelStrip className="mt-8" />
          </div>
          <div id="demo" className="scroll-mt-24">
            <ChatToSlipDemo />
          </div>
        </Container>
      </section>

      <section className="border-y border-border-subtle bg-surface-canvas">
        <Container className="grid grid-cols-2 gap-6 py-8 sm:grid-cols-4">
          {STATS.map(([n, label]) => (
            <div key={label} className="text-center">
              <p className="tabular text-3xl font-extrabold text-netslip-deep">{n}</p>
              <p className="mt-1 text-sm text-ink-secondary">{label}</p>
            </div>
          ))}
        </Container>
      </section>

      <Section>
        <Container>
          <div className="mx-auto max-w-2xl text-center">
            <Text variant="eyebrow" tone="brand">Why NetSlip</Text>
            <Heading className="mt-3">Built for sellers who live in chat.</Heading>
            <Text variant="lead" className="mt-4">Less typing, fewer disputes, and receipts your customers can actually trust.</Text>
          </div>
          <div className="mt-14 grid gap-5 md:grid-cols-3">
            {FEATURES.map((f) => (
              <Card key={f.title} variant="interactive" padding="lg" className="rounded-2xl">
                <span className="inline-flex size-11 items-center justify-center rounded-xl bg-netslip-tint text-netslip-deep [&>svg]:size-5">{f.icon}</span>
                <h3 className="mt-5 text-lg font-bold text-ink-primary">{f.title}</h3>
                <Text className="mt-2">{f.text}</Text>
                <StatusBadge status={f.badge} label={f.badgeLabel} className="mt-5" />
              </Card>
            ))}
          </div>
        </Container>
      </Section>

      <Section tone="canvas">
        <Container className="grid items-center gap-12 lg:grid-cols-2">
          <div>
            <Text variant="eyebrow" tone="brand">Your ledger, your rules</Text>
            <Heading className="mt-3">Locked records. Or audited edits.</Heading>
            <Text variant="lead" className="mt-4">Choose immutable slips with credit notes, or edits that keep a full forensic trail.</Text>
            <ul className="mt-8 space-y-4">
              {[
                [<BadgeCheck key="a" />, 'Every edit stores who, when, why and the before/after.'],
                [<Layers key="b" />, 'Invoices, part-payments, auto-receipts and credit notes in one ledger.'],
                [<Wallet key="c" />, 'Customers pay online by card, transfer, USSD or crypto.'],
              ].map(([icon, text], i) => (
                <li key={i} className="flex gap-3">
                  <span className="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-white text-netslip-primary shadow-sm [&>svg]:size-4">{icon}</span>
                  <Text tone="primary" className="text-base">{text}</Text>
                </li>
              ))}
            </ul>
          </div>
          <Card variant="elevated" padding="none" className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-border-subtle px-5 py-3">
              <p className="text-sm font-semibold">INV-2026-0017 · revision history</p>
              <StatusBadge status="MUTABLE_WITH_AUDIT" showIcon />
            </div>
            <ul className="divide-y divide-border-subtle text-sm">
              {[
                ['Rev 2 · Edit', 'Tobi (Sales)', '“Customer changed size”', 'total ₦61,275 → ₦30,637.50'],
                ['Payment', 'Amina (Owner)', 'Bank transfer · MONIE-88421', 'balance ₦30,637.50 → ₦0'],
                ['Issued', 'WhatsApp bot', 'HMAC 9a84f2…e01c', 'RCP auto-issued on payment'],
              ].map(([a, b, c, d]) => (
                <li key={a} className="grid gap-1 px-5 py-4 sm:grid-cols-[140px_1fr]">
                  <p className="font-semibold text-ink-primary">{a}</p>
                  <div>
                    <p className="text-ink-secondary">{b} · <span className="italic">{c}</span></p>
                    <p className="tabular mt-0.5 text-xs text-ink-muted">{d}</p>
                  </div>
                </li>
              ))}
            </ul>
          </Card>
        </Container>
      </Section>

      <Section>
        <Container>
          <div className="mx-auto max-w-2xl text-center">
            <Text variant="eyebrow" tone="brand">Pricing</Text>
            <Heading className="mt-3">Start free. Pay as you grow.</Heading>
            <Text variant="lead" className="mt-4">No card needed. Credit packs never expire and remove the watermark.</Text>
          </div>
          <PricingCards className="mt-12" />
        </Container>
      </Section>

      <section className="px-4 pb-20 sm:px-6 lg:px-8">
        <div className="relative mx-auto max-w-7xl overflow-hidden rounded-3xl bg-netslip-deep px-6 py-14 text-center sm:px-12">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(255,255,255,0.12),transparent_40%)]" aria-hidden />
          <Tagline className="relative block text-4xl text-white sm:text-5xl" />
          <Text tone="inverse" variant="lead" className="relative mx-auto mt-4 max-w-xl">Set up in two minutes. Your first 15 slips are on us.</Text>
          <div className="relative mt-8 flex flex-wrap justify-center gap-3">
            <Button to="/register" size="lg" variant="white" rightIcon={<ArrowRight className="size-5" />}>Create free account</Button>
            <Button to="/how-it-works" size="lg" variant="deep" className="ring-1 ring-white/25">See how it works</Button>
          </div>
        </div>
      </section>
    </>
  )
}
