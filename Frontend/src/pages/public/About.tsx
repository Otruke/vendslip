import { DatabaseZap, Fingerprint, KeyRound, Lock, ScrollText, ShieldCheck } from 'lucide-react'
import { Button, Card, Container, Heading, Section, Text } from '@/components/ui'
import { VerifyLookup } from './Verify'

const CONTROLS = [
  { icon: <Fingerprint />, title: 'Signed slips', text: 'HMAC-SHA256 fingerprint on every receipt and invoice.' },
  { icon: <Lock />, title: 'Encrypted secrets', text: 'Gateway keys and 2FA secrets encrypted at rest (AES-256).' },
  { icon: <ScrollText />, title: 'Forensic history', text: 'Every edit logs actor, IP, reason and before/after.' },
  { icon: <KeyRound />, title: 'Two-factor sign-in', text: 'Authenticator-app 2FA for owners, staff and admins.' },
  { icon: <DatabaseZap />, title: 'Tenant isolation', text: 'Each business only ever sees its own records.' },
  { icon: <ShieldCheck />, title: 'Verified webhooks', text: 'Twilio, Telegram and payment callbacks are signature-checked.' },
]

export default function About() {
  return (
    <>
      <Section className="pb-12">
        <Container className="text-center">
          <Text variant="eyebrow" tone="brand">Trust & security</Text>
          <Heading level={1} variant="display" className="mx-auto mt-3 max-w-3xl">Receipts you can stand behind.</Heading>
          <Text variant="lead" className="mx-auto mt-5 max-w-2xl">Built in Lagos by engineers with a security-operations mindset. Your ledger is evidence, so we treat it that way.</Text>
        </Container>
      </Section>
      <Section tone="canvas" className="pt-14">
        <Container className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {CONTROLS.map((c) => (
            <Card key={c.title} variant="interactive" padding="lg" className="rounded-2xl">
              <span className="inline-flex size-10 items-center justify-center rounded-xl bg-netslip-tint text-netslip-deep [&>svg]:size-5">{c.icon}</span>
              <h2 className="mt-4 font-bold text-ink-primary">{c.title}</h2>
              <Text className="mt-1.5">{c.text}</Text>
            </Card>
          ))}
        </Container>
      </Section>
      <Section>
        <Container size="sm" className="text-center">
          <Heading>Check any slip in seconds</Heading>
          <Text variant="lead" className="mt-3">Enter the NSV code printed under the QR.</Text>
          <VerifyLookup className="mx-auto mt-8 max-w-md" />
          <Text variant="caption" tone="muted" className="mt-8">Aligned with NDPA and GDPR data minimisation: verification shows initials, never contact details.</Text>
          <Button to="/register" className="mt-6">Start free</Button>
        </Container>
      </Section>
    </>
  )
}
