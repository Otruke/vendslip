import { photos, product } from '@/assets'
import { Photo } from '@/components/media/Photo'
import { Button, Container, Heading, Section, Text } from '@/components/ui'
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
        <Photo image={photos.wallGlass} alt="The NetSlip logo on a dark office wall beside glass meeting rooms" sizes="100vw" eager className="absolute inset-0 -z-10 h-full w-full object-cover" />
        <div className="absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(15,30,36,0.94)_0%,rgba(15,30,36,0.82)_50%,rgba(15,30,36,0.45)_100%)]" aria-hidden />
        <Container className="py-24 lg:py-32">
          <div className="max-w-2xl text-white">
            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#9fd0da]">Trust & security</p>
            <h1 className="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Receipts you can stand behind.</h1>
            <p className="mt-5 text-lg leading-relaxed text-[#d7e8ec]">Built in Lagos by engineers with a security-operations mindset. Your ledger is evidence, so we treat it that way.</p>
          </div>
        </Container>
      </section>

      <Section>
        <Container className="grid gap-12 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
          <div>
            <Text variant="eyebrow" tone="brand">The register</Text>
            <Heading className="mt-3">Six controls on every account.</Heading>
            <Text variant="lead" className="mt-4">No add-ons and no premium tier for safety. Every plan, including Free, gets all six.</Text>
            <Photo image={product.qrProof} alt="QR code and NSV verification code printed on a NetSlip receipt" sizes="260px" className="mt-8 hidden w-60 rounded-md border border-border-subtle bg-white p-1 shadow-sm lg:block" />
          </div>
          <ol className="divide-y divide-dashed divide-slate-300 border-y border-dashed border-slate-300">
            {CONTROLS.map(([title, text], i) => (
              <li key={title} className="grid grid-cols-[3rem_1fr] gap-x-4 py-5 sm:grid-cols-[3rem_12rem_1fr]">
                <span className="tabular font-mono text-sm font-bold text-netslip-primary">{String(i + 1).padStart(2, '0')}</span>
                <h2 className="font-bold text-ink-primary">{title}</h2>
                <p className="col-start-2 mt-1 text-ink-secondary sm:col-start-3 sm:mt-0">{text}</p>
              </li>
            ))}
          </ol>
        </Container>
      </Section>

      <Section tone="canvas">
        <Container size="sm" className="text-center">
          <Heading>Check any slip in seconds</Heading>
          <Text variant="lead" className="mt-3">Enter the NSV code printed under the QR on any NetSlip receipt or invoice.</Text>
          <VerifyLookup className="mx-auto mt-8 max-w-md" />
          <Text variant="caption" tone="muted" className="mt-8">Aligned with NDPA and GDPR data minimisation: verification shows initials, never contact details.</Text>
          <Button to="/register" className="mt-6">Start free</Button>
        </Container>
      </Section>
    </>
  )
}
