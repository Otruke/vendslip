import { ArrowRight } from 'lucide-react'
import type { CSSProperties, ReactNode } from 'react'
import { photos, product, slips } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { ChannelStrip } from '@/components/slip/ChannelStrip'
import { Button, Card, Container, Heading, Section, StatusBadge, Text, Words } from '@/components/ui'

/** Chat pieces styled exactly like the live demo on the home page. */
function Out({ children }: { children: ReactNode }) {
  return <p className="ml-auto w-fit max-w-[88%] rounded-xl rounded-tr-sm bg-[#d8f6cb] px-2.5 py-1.5 font-mono text-[12px] shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" data-reveal="slide">{children}</p>
}

const STEPS = [
  {
    n: '01',
    title: 'Type /slash in chat',
    caption: 'Send fields one at a time or all together. VendSlip stays quiet.',
    visual: (
      <div className="space-y-1.5 rounded-xl bg-[#ece5dc] p-3" data-stagger style={{ '--d': '120ms' } as CSSProperties}>
        <Out>/item = 2x Ankara Dress @ 18,500</Out>
        <Out>/discount = 10%</Out>
        <Out>/name = Ada Obi</Out>
        <p className="mx-auto mt-2 w-fit rounded bg-white/80 px-2 py-0.5 text-[10.5px] text-[#4b5b60]" data-reveal="rise">No replies yet · ₦0 messaging cost</p>
      </div>
    ),
  },
  {
    n: '02',
    title: 'Confirm one summary',
    caption: 'One message with Confirm, Edit and Cancel. It is the only reply.',
    visual: (
      <div className="rounded-xl bg-[#ece5dc] p-3">
        <div className="w-[88%] rounded-xl rounded-tl-sm bg-white px-3 py-2 shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" data-reveal="rise" style={{ '--d': '150ms' } as CSSProperties}>
          <p className="text-[13px] font-semibold">Receipt for Ada Obi</p>
          <p className="tabular text-[12px] text-[#4b5b60]">2 items · Total ₦35,797.50</p>
        </div>
        <div className="mt-0.5 grid w-[88%] gap-0.5 text-[12px] font-semibold text-[#0f7a64]" data-stagger style={{ '--d': '260ms' } as CSSProperties}>
          {['Confirm & send slip', 'Edit', 'Cancel'].map((b) => <span key={b} className="rounded-lg bg-white/95 py-1.5 text-center shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" data-reveal="rise">{b}</span>)}
        </div>
        <p className="mt-2 text-center text-[10.5px] text-[#4b5b60]">In Telegram groups, only the seller sees it.</p>
      </div>
    ),
  },
  {
    n: '03',
    title: 'Share a verified slip',
    caption: 'A branded PDF with QR proof, plus a pay link on invoices.',
    visual: (
      <div className="relative h-[196px] overflow-hidden rounded-xl bg-white/10">
        <div className="absolute left-1/2 top-3 w-[90%] -translate-x-1/2" data-reveal="feed" style={{ '--d': '150ms' } as CSSProperties}>
          <Photo image={slips.receipt} alt="A VendSlip receipt in the Classic Executive design" sizes="340px" className="vs-print w-full rounded-t-md shadow-[0_10px_24px_-10px_rgba(22,53,64,0.45)]" />
        </div>
      </div>
    ),
  },
]

export default function HowItWorks() {
  return (
    <>
      <Section className="pb-12 lg:pb-16">
        <Container className="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <div data-stagger>
            <Text variant="eyebrow" tone="brand" data-reveal="rise">How it works</Text>
            <Heading level={1} variant="display" className="mt-3" data-reveal="type"><Words>Three steps. One message. Done.</Words></Heading>
            <Text variant="lead" className="mt-5 max-w-xl" data-reveal="rise">Sell where your customers already chat, and let VendSlip handle the paperwork and the proof.</Text>
            <div data-reveal="rise"><ChannelStrip className="mt-8" /></div>
          </div>
          <div className="relative">
            <div className="vs-zoom rounded-2xl shadow-[0_30px_60px_-30px_rgba(22,53,64,0.5)]" data-reveal="tear">
              <Photo image={photos.wallLight} alt="The VendSlip logo mounted on an office wall" sizes="(min-width: 1024px) 560px, 100vw" eager className="aspect-[4/3] w-full object-cover" />
            </div>
            <div className="absolute -bottom-5 left-5 rounded-lg bg-white px-4 py-3 shadow-lg sm:left-8" data-reveal="stamp" style={{ '--d': '700ms' } as CSSProperties}>
              <p className="font-mono text-[11px] text-ink-muted">/name = Ada Obi</p>
              <p className="text-sm font-semibold text-ink-primary">Slip RCP-2026-0108 sent</p>
            </div>
          </div>
        </Container>
      </Section>

      <Section tone="canvas" className="pt-16 lg:pt-20">
        <Container className="grid gap-6 lg:grid-cols-3" data-stagger>
          {STEPS.map((s, i) => (
            <div key={s.n} className="flex" data-reveal="feed">
            <Card variant={(['obsidian', 'deep', 'teal'] as const)[i % 3]} padding="lg" className="vs-slip flex min-w-0 flex-1 flex-col">
              <p className="tabular font-mono text-sm font-bold text-[#9fd0da]">{s.n}<span className="ml-2 inline-block h-px w-10 translate-y-[-3px] bg-white/30" aria-hidden /></p>
              <h2 className="mt-3 text-xl font-bold text-white">{s.title}</h2>
              <p className="mt-2 text-sm leading-relaxed text-white/80">{s.caption}</p>
              <div className="mt-6 flex-1">{s.visual}</div>
            </Card>
            </div>
          ))}
        </Container>
      </Section>

      <Section>
        <Container size="lg" className="grid items-center gap-10 lg:grid-cols-2">
          <div data-stagger>
            <Heading data-reveal="type"><Words>Every field you can send</Words></Heading>
            <Text variant="lead" className="mt-3" data-reveal="rise">Short, forgiving syntax. Write 15k, ₦15,000 or 15000 and VendSlip reads them all the same.</Text>
            <div className="mt-8" data-reveal="rise"><Button to="/register" rightIcon={<ArrowRight className="size-4" />}>Try it free</Button></div>
          </div>
          <Card padding="none" className="overflow-hidden rounded-2xl" data-reveal="feed">
            <table className="w-full text-sm">
              <tbody className="divide-y divide-border-subtle" data-stagger="45" style={{ '--d': '250ms' } as CSSProperties}>
                {[
                  ['/name', 'Ada Obi'], ['/item', '2x Shoe @ 15000 | Belt @ 5k'], ['/item', '2x Shoe @ 15000 less 1000 (1,000 off each)'], ['/item', 'Cap @ 4000 less 10% · Belt @ 5000 was 6000'], ['/item', '3x Wrapper @ 3000 less 1500 total (off the three)'], ['/amount', '30000 (instead of items)'],
                  ['/type', 'receipt or invoice'], ['/discount', '2000 or 10% (off the whole order)'], ['/shipping', '3500'], ['/vat', '7.5, off, 7.5 incl'],
                  ['/paid', 'full or 20000'], ['/due', '7d or 30/11/2026'], ['/phone · /address · /note', 'free text'],
                ].map(([k, v], i) => (
                  <tr key={`${k}-${i}`} className="vs-trow" data-reveal="rise"><td className="w-48 px-4 py-2.5 font-mono text-[13px] font-semibold text-vendslip-deep">{k}</td><td className="px-4 py-2.5 text-ink-secondary">{v}</td></tr>
                ))}
              </tbody>
            </table>
          </Card>
        </Container>
      </Section>

      <Section tone="canvas">
        <Container size="lg" className="grid items-center gap-12 lg:grid-cols-2">
          <div className="relative mx-auto w-full max-w-md">
            <div data-reveal="feed">
              <Photo image={slips.invoice} alt="A VendSlip invoice with a part payment, balance due and QR proof" sizes="(min-width: 1024px) 440px, 90vw" className="rounded-md shadow-[0_24px_50px_-24px_rgba(22,53,64,0.5)]" />
            </div>
            <span className="vs-scan absolute -bottom-8 -right-4 block w-44 rounded-md border border-border-subtle bg-white p-1 shadow-lg sm:-right-8" data-reveal="stamp" style={{ '--d': '650ms' } as CSSProperties}>
              <Photo image={product.qrProof} alt="" sizes="180px" className="w-full" />
            </span>
          </div>
          <div data-stagger>
            <Heading data-reveal="type"><Words>Proof your customers can check.</Words></Heading>
            <Text variant="lead" className="mt-3" data-reveal="rise">Each slip carries an HMAC-SHA256 fingerprint, so any change after issue shows up instantly.</Text>
            <div className="mt-6 flex flex-wrap gap-2" data-stagger><span className="inline-block" data-reveal="stamp"><StatusBadge status="VERIFIED" showIcon /></span><span className="inline-block" data-reveal="stamp"><StatusBadge status="IMMUTABLE" showIcon /></span></div>
            <div className="mt-8" data-reveal="rise"><Button to="/verify" variant="outline">Verify a slip</Button></div>
          </div>
        </Container>
      </Section>
    </>
  )
}
