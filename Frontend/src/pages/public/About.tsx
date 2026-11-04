import { photos, product } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Button, Container, Heading, Section, Text, Words } from '@/components/ui'
import { VerifyLookup } from './Verify'

/** Security controls as a numbered register: the way an auditor would read them. */
const CONTROLS = [
  ['Signed slips', 'Every receipt and invoice carries an HMAC-SHA256 fingerprint.'],
  ['Encrypted secrets', 'Gateway keys and 2FA secrets are encrypted at rest.'],
  ['Forensic history', 'Every edit records the actor, IP, reason and before and after.'],
  ['Two-factor sign-in', 'Authenticator-app 2FA for owners, staff and platform admins.'],
  ['Tenant isolation', 'Each business only ever sees its own records.'],
  ['Verified webhooks', 'Twilio, Telegram and payment callbacks are signature-checked.'],
]

export default function About() {
  return (
    <>
      <section className="relative isolate overflow-hidden">
        <Photo image={photos.wallGlass} alt="The VendSlip logo on a dark office wall beside glass meeting rooms" sizes="100vw" eager className="absolute inset-x-0 -top-[12%] -z-10 h-[124%] w-full object-cover" data-parallax="0.16" />
        <div className="absolute inset-0 -z-10 bg-vendslip-obsidian/85" aria-hidden />
        <Container className="py-24 lg:py-32">
          <div className="max-w-2xl text-white" data-stagger>
            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#9fd0da]" data-reveal="rise">Trust & security</p>
            <h1 className="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl" data-reveal="type"><Words>Receipts you can stand behind.</Words></h1>
            <p className="mt-5 text-lg leading-relaxed text-[#d7e8ec]" data-reveal="rise">Built in Lagos by engineers with a security-operations mindset. Your ledger is evidence, so we treat it that way.</p>
          </div>
        </Container>
      </section>

      <Section>
        <Container className="grid gap-12 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
          <div data-stagger>
            <Text variant="eyebrow" tone="brand" data-reveal="rise">The register</Text>
            <Heading className="mt-3" data-reveal="type"><Words>Six controls on every account.</Words></Heading>
            <Text variant="lead" className="mt-4" data-reveal="rise">No add-ons and no premium tier for safety. Every plan, including Free, gets all six.</Text>
            <span className="vs-scan mt-8 hidden w-60 rounded-md border border-border-subtle bg-white p-1 shadow-sm lg:block" data-reveal="stamp">
              <Photo image={product.qrProof} alt="QR code and VSV verification code printed on a VendSlip receipt" sizes="260px" className="w-full" />
            </span>
          </div>
          <ol className="divide-y divide-dashed divide-slate-300 border-y border-dashed border-slate-300" data-stagger>
            {CONTROLS.map(([title, text], i) => (
              <li key={title} className="vs-row grid grid-cols-[3rem_1fr] gap-x-4 py-5 sm:grid-cols-[3rem_12rem_1fr]" data-reveal="rise">
                <span className="tabular font-mono text-sm font-bold text-vendslip-primary">{String(i + 1).padStart(2, '0')}</span>
                <h2 className="font-bold text-ink-primary">{title}</h2>
                <p className="col-start-2 mt-1 text-ink-secondary sm:col-start-3 sm:mt-0">{text}</p>
              </li>
            ))}
          </ol>
        </Container>
      </Section>

      <Section tone="canvas">
        <Container size="sm" className="text-center" data-stagger>
          <Heading data-reveal="type"><Words>Check any slip in seconds</Words></Heading>
          <Text variant="lead" className="mt-3" data-reveal="rise">Enter the VSV code printed under the QR on any VendSlip receipt or invoice.</Text>
          <div data-reveal="rise"><VerifyLookup className="mx-auto mt-8 max-w-md" /></div>
          <Text variant="caption" tone="muted" className="mt-8" data-reveal="rise">Aligned with NDPA and GDPR data minimisation: verification shows initials, never contact details.</Text>
          <div className="mt-6" data-reveal="rise"><Button to="/register">Start free</Button></div>
        </Container>
      </Section>
    </>
  )
}
