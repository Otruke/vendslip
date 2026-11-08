import { useQueryClient } from '@tanstack/react-query'
import { ArrowUpRight, FileText, ShieldCheck, X } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alert, Button, Checkbox, Modal } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { errorMessage, resetCsrf } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Session } from '@/types/api'

const longDate = (iso: string) => {
  const d = new Date(`${iso}T00:00:00`)
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

/**
 * New Terms of Service / Privacy Policy versions.
 *  - Before the new version applies: a slim notice under the header, with "Review" (closes for this visit).
 *  - Once it applies: a screen that can't be closed until they accept (or sign out). The server also
 *    refuses changes until then. Not shown in support sessions: staff can't accept for the owner.
 */
export function LegalUpdate() {
  const legal = useAuthStore((s) => s.session?.legal)
  const inSupport = useAuthStore((s) => Boolean(s.session?.support))
  const [hidden, setHidden] = useState(false)
  const [reviewing, setReviewing] = useState(false)

  if (!legal || legal.up_to_date || inSupport) return null
  if (legal.required) return <LegalDialog required />

  return (
    <>
      {!hidden && (
        <div className="flex items-center gap-2 border-t border-border-subtle bg-vendslip-tint px-4 py-1.5 text-xs text-vendslip-deep sm:px-6 lg:px-8">
          <FileText className="size-3.5 shrink-0" aria-hidden />
          <p className="min-w-0 flex-1 truncate">Our Terms and Privacy Policy change on <strong className="font-semibold">{longDate(legal.effective_on)}</strong>.</p>
          <button type="button" onClick={() => setReviewing(true)} className="shrink-0 font-semibold underline-offset-2 hover:underline">Review</button>
          <button type="button" onClick={() => setHidden(true)} aria-label="Hide for now" className="shrink-0 rounded p-0.5 text-vendslip-deep/70 hover:bg-white/60 hover:text-vendslip-deep"><X className="size-3.5" /></button>
        </div>
      )}
      {reviewing && <LegalDialog onClose={() => setReviewing(false)} />}
    </>
  )
}

function LegalDialog({ required = false, onClose }: { required?: boolean; onClose?: () => void }) {
  const session = useAuthStore((s) => s.session)
  const setSession = useAuthStore((s) => s.setSession)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [agreed, setAgreed] = useState(false)
  const [busy, setBusy] = useState<'accept' | 'out' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const legal = session?.legal
  if (!legal) return null
  const firstTime = legal.accepted_version === null

  const accept = async () => {
    setBusy('accept'); setError(null)
    try {
      setSession(await apiPost<Session>('/legal/accept', { accept_terms: true }))
      onClose?.()
    } catch (e) { setError(errorMessage(e)); setBusy(null) }
  }
  const signOut = async () => {
    setBusy('out')
    try { await apiPost('/auth/logout') } finally {
      resetCsrf(); queryClient.clear(); setSession(null); navigate('/login', { replace: true })
    }
  }

  return (
    <Modal
      isOpen
      size="sm"
      dismissible={!required}
      onClose={onClose ?? (() => undefined)}
      title={firstTime ? 'Please accept our terms' : 'We’ve updated our terms'}
      description={`Version ${legal.version} · ${required ? 'applies now' : `applies from ${longDate(legal.effective_on)}`}`}
      footer={required
        ? <>
            <Button variant="ghost" onClick={signOut} isLoading={busy === 'out'} disabled={busy !== null}>Sign out</Button>
            <Button onClick={accept} isLoading={busy === 'accept'} disabled={!agreed || busy !== null}>Accept and continue</Button>
          </>
        : <>
            <Button variant="ghost" onClick={onClose}>Not now</Button>
            <Button onClick={accept} isLoading={busy === 'accept'} disabled={!agreed || busy !== null}>Accept</Button>
          </>}
    >
      <div className="space-y-4">
        {legal.summary && !firstTime && (
          <div className="rounded-xl bg-vendslip-tint px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-vendslip-deep">What changed</p>
            <p className="mt-1 text-sm leading-relaxed text-ink-primary">{legal.summary}</p>
          </div>
        )}
        {firstTime && <p className="text-sm text-ink-secondary">We don’t have a record of you accepting them yet. They explain how VendSlip works and how we handle your data.</p>}
        <div className="grid gap-2 sm:grid-cols-2">
          {[
            { href: '/terms', label: 'Terms of Service', icon: <FileText className="size-4" /> },
            { href: '/privacy', label: 'Privacy Policy', icon: <ShieldCheck className="size-4" /> },
          ].map((doc) => (
            <a key={doc.href} href={doc.href} target="_blank" rel="noopener"
              className="flex items-center gap-2 rounded-lg border border-border-subtle px-3 py-2.5 text-sm font-medium text-ink-primary transition-colors hover:border-vendslip-primary/50 hover:text-vendslip-primary">
              <span className="text-vendslip-primary">{doc.icon}</span>{doc.label}<ArrowUpRight className="ml-auto size-3.5 text-ink-muted" aria-hidden />
            </a>
          ))}
        </div>
        <Checkbox className="items-start text-ink-secondary [&>input]:mt-0.5" checked={agreed} onChange={(e) => setAgreed(e.target.checked)}
          label="I’ve read and agree to the Terms of Service and Privacy Policy." />
        {error && <Alert tone="danger">{error}</Alert>}
      </div>
    </Modal>
  )
}
