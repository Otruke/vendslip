import { FileText, ShieldCheck } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useLegalInfo, type LegalInfo } from '@/hooks/useLegalInfo'

/**
 * VendSlip's Terms of Service and Privacy Policy.
 *
 * Written to meet the transparency and contract requirements of the EU/UK GDPR, Nigeria's Data
 * Protection Act 2023 (NDPA) and GAID 2025, Kenya's Data Protection Act 2019, Ghana's Data
 * Protection Act 2012 and South Africa's POPIA, and to follow ISO/IEC 27001 / 27701 practice.
 * The version shown here must match config('vendslip.legal.version') on the server: people accept
 * that version at sign-up, when joining a team and before every payment.
 */

function effectiveDate(version: string): string {
  const d = new Date(`${version}T00:00:00`)
  return Number.isNaN(d.getTime()) ? version : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

type Section = { id: string; title: string; body: ReactNode }

function LegalPage({ kind, title, intro, sections, info }: { kind: 'terms' | 'privacy'; title: string; intro: ReactNode; sections: Section[]; info: LegalInfo }) {
  return (
    <div className="bg-surface-canvas">
      <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:py-16">
        <header className="max-w-3xl">
          <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-vendslip-primary">
            {kind === 'privacy' ? <ShieldCheck className="size-4" aria-hidden /> : <FileText className="size-4" aria-hidden />}Legal
          </p>
          <h1 className="mt-2 font-display text-3xl font-bold text-ink-primary sm:text-4xl">{title}</h1>
          <p className="mt-3 text-sm text-ink-secondary">Version {info.version} · {info.in_force ? 'effective' : 'takes effect on'} {effectiveDate(info.version)}</p>
          {info.summary && (
            <div className="mt-4 rounded-xl border-l-[3px] border-vendslip-primary bg-white px-4 py-3 text-sm">
              <p className="font-semibold text-ink-primary">What changed in this version</p>
              <p className="mt-1 leading-relaxed text-ink-secondary">{info.summary}</p>
            </div>
          )}
          <div className="mt-5 space-y-3 text-base leading-relaxed text-ink-secondary">{intro}</div>
          <p className="mt-4 text-sm">
            {kind === 'privacy'
              ? <>See also our <Link to="/terms" className="font-semibold text-vendslip-primary hover:underline">Terms of Service</Link>.</>
              : <>See also our <Link to="/privacy" className="font-semibold text-vendslip-primary hover:underline">Privacy Policy</Link>.</>}
          </p>
        </header>

        <div className="mt-10 grid gap-10 lg:grid-cols-[240px_1fr]">
          <nav aria-label="Contents" className="lg:sticky lg:top-24 lg:self-start">
            <p className="text-xs font-semibold uppercase tracking-wider text-ink-muted">Contents</p>
            <ol className="mt-3 space-y-1.5 text-sm">
              {sections.map((s, i) => (
                <li key={s.id}><a href={`#${s.id}`} className="text-ink-secondary hover:text-vendslip-primary">{i + 1}. {s.title}</a></li>
              ))}
            </ol>
          </nav>
          <article className="max-w-3xl space-y-10">
            {sections.map((s, i) => (
              <section key={s.id} id={s.id} className="scroll-mt-24">
                <h2 className="text-xl font-semibold text-ink-primary">{i + 1}. {s.title}</h2>
                <div className="legal-body mt-3 space-y-3 text-[15px] leading-relaxed text-ink-secondary [&_li]:ml-5 [&_li]:list-disc [&_strong]:text-ink-primary [&_ul]:space-y-1.5">{s.body}</div>
              </section>
            ))}
          </article>
        </div>
      </div>
    </div>
  )
}

function Mail({ to }: { to: string }) {
  return <a href={`mailto:${to}`} className="font-semibold text-vendslip-primary hover:underline">{to}</a>
}

function Table({ head, rows }: { head: string[]; rows: ReactNode[][] }) {
  return (
    <div className="overflow-x-auto rounded-xl border border-border-subtle bg-white">
      <table className="w-full min-w-[560px] text-left text-sm">
        <thead className="bg-surface-subtle text-xs uppercase tracking-wider text-ink-muted"><tr>{head.map((h) => <th key={h} className="px-3 py-2 font-semibold">{h}</th>)}</tr></thead>
        <tbody className="divide-y divide-border-subtle">{rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j} className="px-3 py-2 align-top">{c}</td>)}</tr>)}</tbody>
      </table>
    </div>
  )
}

export function Privacy() {
  const info = useLegalInfo()
  const sections: Section[] = [
    {
      id: 'who', title: 'Who we are', body: <>
        <p>VendSlip is operated by <strong>{info.entity}</strong>, {info.address} (“VendSlip”, “we”, “us”).</p>
        <p>For questions about your personal data, or to use any of your rights, email our data protection contact at <Mail to={info.privacy_email} />. We reply within 30 days, and sooner where the law requires.</p>
      </>,
    },
    {
      id: 'roles', title: 'Our two roles', body: <>
        <p><strong>Data controller.</strong> We decide how and why personal data is used for VendSlip accounts, team members, billing, security and support.</p>
        <p><strong>Data processor.</strong> When a business uses VendSlip to issue receipts and invoices, the details of <em>its</em> customers (names, phone numbers, emails, addresses, items bought) are processed on the business’s instructions. The business is the controller of that data. We handle it only to provide the service, under the data processing terms in our <Link to="/terms#data-processing" className="font-semibold text-vendslip-primary hover:underline">Terms of Service</Link>.</p>
        <p>If you received a slip from a business and want to know what it holds about you, contact that business first. We will help it respond.</p>
      </>,
    },
    {
      id: 'data', title: 'What we collect', body: <>
        <ul>
          <li><strong>Account data:</strong> name, email, phone, password (stored only as a one-way hash), two-factor settings, role and profile photo.</li>
          <li><strong>Business data:</strong> business name, address, country, state and city, registration type and number (RC/BN), tax ID, logo, branding, VAT settings, and the bank accounts and crypto wallets you choose to show on slips.</li>
          <li><strong>Slip and customer data</strong> (as processor): customer names, phones, emails and addresses, items, prices, discounts, payments, due dates and notes.</li>
          <li><strong>Chat channel data:</strong> your WhatsApp, Telegram, Messenger or Instagram account ID and the messages you send to the VendSlip bot to create slips.</li>
          <li><strong>Payment data:</strong> amount, currency, plan, status and reference of payments to VendSlip. Card and wallet details are entered on our payment providers’ pages. <strong>We never see or store full card numbers.</strong></li>
          <li><strong>Technical and security data:</strong> IP address, browser and device type, sign-in times, and an audit log of changes made in the account.</li>
          <li><strong>Support data:</strong> what you write in support tickets and our replies.</li>
        </ul>
      </>,
    },
    {
      id: 'purposes', title: 'Why we use it and our legal basis', body: <>
        <Table head={['Purpose', 'Legal basis']} rows={[
          ['Create your account and provide VendSlip (slips, invoices, chats, inventory, reminders)', 'Contract'],
          ['Take payments for plans and credits, and keep billing records', 'Contract; legal obligation (tax and accounting)'],
          ['Keep accounts secure: two-factor sign-in, audit logs, fraud and abuse prevention', 'Legitimate interests; legal obligation (security of processing)'],
          ['Send service emails: alerts, receipts, renewal and invoice reminders, security notices', 'Contract; legitimate interests'],
          ['Answer support tickets and, only with the owner’s one-time code, open a support session', 'Contract; consent of the business owner for support sessions'],
          ['Improve VendSlip using aggregated, non-identifying usage figures', 'Legitimate interests'],
          ['Anonymous website activity notes (e.g. “A business in Ikeja just issued a receipt”), with no names or amounts; each business can switch this off', 'Legitimate interests (you can object at any time)'],
          ['Comply with law, court orders and lawful requests from regulators', 'Legal obligation'],
        ]} />
        <p>We do not sell personal data, and we do not use it for advertising profiles. We make no automated decisions that have legal or similarly significant effects on you.</p>
      </>,
    },
    {
      id: 'cookies', title: 'Cookies and local storage', body: <>
        <p>We use only what VendSlip needs to work:</p>
        <ul>
          <li>A session cookie that keeps you signed in, and an XSRF-TOKEN cookie that protects forms against cross-site request forgery.</li>
          <li>Browser storage for small preferences, such as your pricing currency or whether you hid website notes.</li>
        </ul>
        <p>We do not use advertising or cross-site tracking cookies. Because these are strictly necessary, no cookie banner is required. If we ever add optional analytics, we will ask first.</p>
      </>,
    },
    {
      id: 'sharing', title: 'Who we share it with', body: <>
        <p>We share personal data only with service providers that help us run VendSlip. Each one is bound by a contract that limits its use of the data:</p>
        <ul>
          <li><strong>Hosting and infrastructure</strong> providers that store and run VendSlip.</li>
          <li><strong>Payment providers:</strong> Flutterwave (cards, transfers, USSD) and NOWPayments (crypto). Both follow PCI DSS and process payment details under their own privacy policies.</li>
          <li><strong>Messaging platforms</strong> you connect: Meta (WhatsApp, Messenger, Instagram), Telegram and Twilio, to deliver and receive bot messages.</li>
          <li><strong>Email delivery</strong> providers, to send alerts, receipts and reminders.</li>
          <li><strong>Professional advisers, authorities and courts</strong> where the law requires it, or to protect rights, safety and property.</li>
          <li>A <strong>buyer or successor</strong> if VendSlip is merged or sold. They remain bound by this policy.</li>
        </ul>
        <p>The exchange-rate service we use to price plans receives no personal data.</p>
      </>,
    },
    {
      id: 'transfers', title: 'International transfers', body: <>
        <p>Some providers process data outside Nigeria, the EU/UK or your country. When they do, we rely on an adequacy decision, standard contractual clauses or other safeguards recognised by the GDPR (Chapter V) and the NDPA (Part VIII). These keep your data protected to the same standard. Email us for a copy of the safeguards used.</p>
      </>,
    },
    {
      id: 'retention', title: 'How long we keep it', body: <>
        <ul>
          <li><strong>Account and business data:</strong> while your account is open, then deleted or anonymised within 90 days of closure, except as below.</li>
          <li><strong>Slips, invoices, payments and billing records:</strong> for as long as tax and accounting law requires, normally <strong>6 years</strong> after the transaction in Nigeria, longer if your local law requires.</li>
          <li><strong>Security and audit logs:</strong> up to 24 months, or longer when needed for an investigation or legal claim.</li>
          <li><strong>Support tickets:</strong> up to 24 months after closure.</li>
          <li><strong>Chat drafts that were never issued</strong> expire automatically.</li>
        </ul>
      </>,
    },
    {
      id: 'security', title: 'How we protect it', body: <>
        <p>Our security programme follows the ISO/IEC 27001 and ISO/IEC 27701 frameworks. It includes:</p>
        <ul>
          <li>Encryption in transit (HTTPS/TLS). Payment gateway keys are encrypted at rest.</li>
          <li>Hashed passwords, strong password rules and two-factor sign-in.</li>
          <li>Role-based access for teams. Owners approve any VendSlip support session with a one-time code.</li>
          <li>A tamper-evident audit trail and signed slip verification codes, so altered slips can be detected.</li>
          <li>Payment-account changes need a password and alert the business’s owners straight away.</li>
          <li>Least-privilege staff access, confidentiality duties and an incident response process.</li>
        </ul>
        <p>No system is perfectly secure. If a breach is likely to put your rights at risk, we will notify the relevant authority within 72 hours of becoming aware of it, as GDPR Art. 33 and NDPA s.40 require. We will also tell affected people without undue delay.</p>
      </>,
    },
    {
      id: 'rights', title: 'Your rights', body: <>
        <p>Depending on where you live, you have the right to:</p>
        <ul>
          <li><strong>access</strong> your data and get a copy;</li>
          <li><strong>correct</strong> inaccurate data;</li>
          <li><strong>delete</strong> data, where we are not required to keep it;</li>
          <li><strong>restrict</strong> or <strong>object to</strong> certain uses, including those based on legitimate interests;</li>
          <li><strong>data portability</strong>: receive your data in a machine-readable format;</li>
          <li><strong>withdraw consent</strong> at any time, without affecting earlier processing.</li>
        </ul>
        <p>Email <Mail to={info.privacy_email} />. We may ask you to confirm your identity first. Using your rights is free.</p>
        <p>You can also complain to a data protection authority. These include the <strong>Nigeria Data Protection Commission</strong> (ndpc.gov.ng), the authority in your EU country, the UK <strong>ICO</strong>, Kenya’s <strong>ODPC</strong>, Ghana’s <strong>Data Protection Commission</strong> or South Africa’s <strong>Information Regulator</strong>. We would appreciate the chance to put things right first.</p>
      </>,
    },
    {
      id: 'children', title: 'Children', body: <p>VendSlip is a business service for people aged 18 and over. We do not knowingly collect children’s data. If you believe a child has given us data, contact us and we will delete it.</p>,
    },
    {
      id: 'changes', title: 'Changes to this policy', body: <p>We may update this policy as VendSlip or the law changes. The version and effective date are shown at the top of this page. For material changes, we will tell account owners by email and in the app, show what changed, and ask everyone with an account to accept the new version.</p>,
    },
  ]
  return (
    <LegalPage kind="privacy" title="Privacy Policy" info={info} sections={sections} intro={<>
      <p>This policy explains what personal data VendSlip collects, why, who it is shared with, how long it is kept and the rights you have. It is written to meet the EU and UK GDPR, Nigeria’s Data Protection Act 2023 and its General Application and Implementation Directive, Kenya’s Data Protection Act 2019, Ghana’s Data Protection Act 2012 and South Africa’s POPIA.</p>
    </>} />
  )
}

export function Terms() {
  const info = useLegalInfo()
  const sections: Section[] = [
    {
      id: 'agreement', title: 'The agreement', body: <>
        <p>These Terms are an agreement between you and <strong>{info.entity}</strong>, {info.address} (“VendSlip”). If you use VendSlip for a business, you accept them for that business and confirm you are allowed to.</p>
        <p>You accept them when you create an account, join a team, or tick the box before a payment. We keep a record of the version you accepted, when, and from which device.</p>
        <p>You must be 18 or over. VendSlip is for business use, not personal or household use.</p>
      </>,
    },
    {
      id: 'service', title: 'What VendSlip does', body: <p>VendSlip lets businesses create receipts, invoices and credit notes from the dashboard or from chat apps (WhatsApp, Telegram, Messenger and Instagram). It also lets them verify slips, track stock, collect invoice payments through their own payment accounts, and manage their team. Features depend on your plan, and we may improve or change them over time.</p>,
    },
    {
      id: 'accounts', title: 'Your account and team', body: <ul>
      <li>Keep your sign-in details secret, and turn on two-factor sign-in. You are responsible for activity in your account.</li>
      <li>Owners control who joins the team and what each role can do, and are responsible for their team’s use of VendSlip.</li>
      <li>Tell us straight away at <Mail to={info.support_email} /> if you suspect unauthorised access.</li>
      <li>VendSlip staff only enter your dashboard after an Owner shares a one-time code. Every action in that session is recorded in your Activity log.</li>
    </ul>,
    },
    {
      id: 'responsibilities', title: 'Your responsibilities for slips', body: <ul>
      <li>You are responsible for the content of every slip: customer details, items, prices, VAT and taxes.</li>
      <li>You are responsible for meeting the tax, invoicing and consumer laws that apply to your business. In Nigeria, that includes any FIRS or state e-invoicing rules. A VendSlip verification code proves a slip came from VendSlip unchanged; it is not a government tax certificate.</li>
      <li>You must have a lawful basis to give us your customers’ details and to message them through chat apps or email.</li>
      <li>Your ledger edit policy (locked, edit with reason, or open) is set by VendSlip at your request, and every change is kept in the slip’s history.</li>
    </ul>,
    },
    {
      id: 'acceptable-use', title: 'Acceptable use', body: <>
        <p>You must not use VendSlip to:</p>
        <ul>
          <li>create false, misleading or fraudulent receipts or invoices, or slips for sales that did not happen;</li>
          <li>sell or promote illegal goods or services, or launder money;</li>
          <li>send spam, or message people who have not agreed to hear from you;</li>
          <li>break into, overload, reverse-engineer or scrape VendSlip, or get around plan limits or security controls;</li>
          <li>infringe anyone’s rights, or upload malicious code.</li>
        </ul>
        <p>We may remove content, suspend accounts or report activity to the authorities when we reasonably believe these Terms or the law have been broken.</p>
      </>,
    },
    {
      id: 'plans', title: 'Plans, payments and renewal', body: <ul>
      <li><strong>Prepaid plans.</strong> Plans are paid in advance for a month or a year. <strong>They do not renew automatically, and we never charge you without your action.</strong> We remind Owners before a plan ends. If it isn’t renewed, the business moves to the Free plan and nothing is deleted.</li>
      <li><strong>Early renewal</strong> adds the new period after your current one ends, so no days are lost.</li>
      <li><strong>Changing plan</strong> mid-term turns the unused value of your current plan into extra time on the new one.</li>
      <li><strong>Credit packs</strong> do not expire while your account is open.</li>
      <li><strong>Prices</strong> are shown before you pay and may be converted from US dollars at the day’s exchange rate. The amount is fixed when you start a payment. Taxes are added where required.</li>
      <li><strong>Payment providers.</strong> Payments are processed by Flutterwave or NOWPayments under their own terms. If a gateway can’t charge your currency, it charges US dollars.</li>
      <li>Do not file a chargeback for a payment you made without contacting us first. Unjustified chargebacks may lead to suspension.</li>
    </ul>,
    },
    {
      id: 'refunds', title: 'Refunds and cancellation', body: <ul>
      <li>You can stop using a paid plan at any time. Simply don’t renew it; you keep the features until the end of the paid period.</li>
      <li>Payments for plans and used credits are non-refundable, except where these Terms or the law say otherwise.</li>
      <li>If you were charged twice, charged the wrong amount, or VendSlip was unavailable for a long stretch of your paid period because of us, email <Mail to={info.support_email} /> within 14 days. We will refund or extend your plan.</li>
      <li>Unused credit packs bought in the last 14 days can be refunded on request.</li>
      <li>Nothing here limits your rights under consumer protection laws that cannot be excluded.</li>
    </ul>,
    },
    {
      id: 'customer-payments', title: 'Payments from your customers', body: <p>When your customers pay an invoice through VendSlip, they pay through your own Flutterwave or NOWPayments account, using keys you provide. VendSlip does not hold, receive or settle that money and is not a party to the sale. Your payment provider’s terms, fees and settlement times apply. You are responsible for refunds and disputes with your customers.</p>,
    },
    {
      id: 'data-processing', title: 'Data processing terms', body: <>
        <p>For your customers’ personal data, you are the controller and VendSlip is your processor. To meet GDPR Art. 28 and NDPA s.29, VendSlip will:</p>
        <ul>
          <li>process it only on your documented instructions (your use of the service), unless the law requires otherwise;</li>
          <li>ensure everyone with access is bound by confidentiality;</li>
          <li>apply the security measures described in our Privacy Policy;</li>
          <li>use only sub-processors bound by equivalent terms, and keep their list in the Privacy Policy;</li>
          <li>help you respond to data subject requests and meet your security, breach-notification and impact-assessment duties;</li>
          <li>tell you without undue delay about a personal data breach affecting your data;</li>
          <li>delete or return the data when you close your account, except where the law requires us to keep it;</li>
          <li>give you the information needed to show compliance, and allow reasonable audits on notice.</li>
        </ul>
      </>,
    },
    {
      id: 'third-parties', title: 'Third-party services', body: <p>Chat apps, payment providers and email services are run by third parties under their own terms. Your use of them through VendSlip must follow those terms; for example, WhatsApp Business messaging rules. We are not responsible for their outages or changes, but we will work to keep VendSlip connected.</p>,
    },
    {
      id: 'ip', title: 'Intellectual property', body: <p>VendSlip, its software, templates, design and brand belong to {info.entity} or its licensors. You keep ownership of your content, including your logo, slips and customer lists. You give us a limited licence to host and process that content only to provide VendSlip to you. You may not copy or resell VendSlip.</p>,
    },
    {
      id: 'availability', title: 'Availability and changes', body: <p>We work to keep VendSlip available and secure, but we don’t promise it will always be uninterrupted or error-free. We may carry out maintenance, and will give notice of planned downtime where we can. We may change features. If a change materially reduces a paid feature during your paid period, you may ask for a pro-rata refund.</p>,
    },
    {
      id: 'termination', title: 'Suspension and closing your account', body: <ul>
      <li>You can close your account at any time by contacting us. Ask within 30 days and we will export your slips and data for you.</li>
      <li>We may suspend or close an account for serious or repeated breaches of these Terms, fraud, non-payment, or if the law requires it. Where we reasonably can, we will tell you first and give you a chance to fix the issue.</li>
      <li>After closure we delete data as described in the Privacy Policy, keeping what the law requires.</li>
    </ul>,
    },
    {
      id: 'liability', title: 'Liability', body: <>
        <p>Apart from the promises in these Terms, VendSlip is provided “as is”. To the extent the law allows:</p>
        <ul>
          <li>we are not liable for indirect or consequential loss, lost profits or lost data that you could reasonably have backed up;</li>
          <li>our total liability for any claim is limited to the fees you paid VendSlip in the 12 months before the event that caused it.</li>
        </ul>
        <p>Nothing limits liability for death or personal injury caused by negligence, fraud, or anything else that cannot legally be limited.</p>
        <p>You will compensate us for claims caused by your unlawful use of VendSlip or your breach of these Terms. This includes slips you issue and messages you send.</p>
      </>,
    },
    {
      id: 'law', title: 'Governing law and disputes', body: <p>These Terms are governed by the laws of the Federal Republic of Nigeria. We will first try to resolve any dispute in good faith; write to <Mail to={info.support_email} />. If it is not resolved within 30 days, the courts of Lagos State have jurisdiction. If you are a consumer, you keep the protection of the mandatory laws of the country where you live.</p>,
    },
    {
      id: 'changes', title: 'Changes to these Terms', body: <p>We may update these Terms. The version and date are shown at the top. For material changes, we will tell account owners in advance by email and in the app, with a short summary of what changed. From the effective date, you will be asked to accept the new version before you can continue using your account. If you don’t agree, you may stop using VendSlip and ask for a pro-rata refund of the unused part of your paid period.</p>,
    },
    {
      id: 'contact', title: 'Contact', body: <p>{info.entity}, {info.address}. Support: <Mail to={info.support_email} />. Privacy: <Mail to={info.privacy_email} />.</p>,
    },
  ]
  return (
    <LegalPage kind="terms" title="Terms of Service" info={info} sections={sections} intro={<>
      <p>These Terms set out how you may use VendSlip, what you can expect from us and what we expect from you. Please read them: they include how payments, refunds, your customers’ data and liability work.</p>
    </>} />
  )
}

/** "I agree to the Terms of Service and Privacy Policy" with links that open in a new tab. */
export function LegalAgreement({ verb = 'I agree to' }: { verb?: string }) {
  return (
    <span>
      {verb} VendSlip’s{' '}
      <a href="/terms" target="_blank" rel="noopener" className="font-semibold text-vendslip-primary hover:underline">Terms of Service</a>
      {' '}and{' '}
      <a href="/privacy" target="_blank" rel="noopener" className="font-semibold text-vendslip-primary hover:underline">Privacy Policy</a>.
    </span>
  )
}
