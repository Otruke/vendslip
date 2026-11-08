# VendSlip regulatory brief for legal review

Nov 8, 2026 · @Rufus

## Purpose of this brief

VendSlip has a Privacy Policy and Terms of Service written to GDPR, Nigeria's NDPA 2023 and GAID 2025, and the main African data laws, but **no lawyer has reviewed them yet**. This brief gives whoever takes them to a lawyer everything in one place: which laws we aimed at, how the product applies each one, what is recorded as evidence, and the decisions a lawyer must confirm.

How to use it:

1. Send the lawyer this brief together with the two live pages: `/terms` and `/privacy` on the VendSlip website.
2. Walk through **Gaps, assumptions and decisions** first. Those are the business choices that carry legal risk.
3. Use **Questions to take to the lawyer** as the meeting agenda.
4. After review, publish the corrected wording as a new version from Super-Admin → Terms & Privacy. Everyone is then asked to accept it.

This brief describes what is built; it is not legal advice.

## What VendSlip is and our role under data law

VendSlip is a business tool run by **Techberryz Futurecore Limited, Lagos**. Shops create receipts, invoices and credit notes from a web dashboard or by chatting with a bot on WhatsApp, Telegram, Messenger or Instagram. They pay VendSlip for plans or credit packs by card or transfer (Flutterwave) or crypto (NOWPayments). They can also collect invoice payments from their own customers through **their own** gateway accounts; that money never passes through VendSlip.

This gives VendSlip **two legal roles**, and most obligations depend on which one applies:

| Role | Whose data | What it covers |
| --- | --- | --- |
| **Controller** | Account holders (business owners and staff) | Accounts, team members, billing, security logs, support tickets, chat account IDs |
| **Processor** | The businesses' own customers | Names, phones, emails, addresses and purchases on each slip. The business is the controller; VendSlip acts on its instructions |

VendSlip is a B2B service for people aged 18 and over. Account holders are mostly in Nigeria, but the Terms and pricing allow businesses anywhere, including Kenya, Ghana, South Africa, the UK and the EU.

## Regulations and standards we aligned with

The policies were written to the strictest common standard (GDPR), then checked against each African law. Only the Nigerian law certainly applies today; the others apply once we have users in those countries.

| Law or standard | Applies because | Provisions the policies cite or follow |
| --- | --- | --- |
| **Nigeria Data Protection Act 2023 (NDPA)** | Nigerian company, Nigerian users | Lawful bases, processor terms (s.29), breach notice to NDPC within 72 h (s.40), cross-border transfers (Part VIII), data subject rights |
| **NDPC General Application and Implementation Directive 2025 (GAID)** | Implements the NDPA; replaced the 2019 NDPR | Registration and annual Compliance Audit Return for controllers "of major importance"; privacy notice content; DPO |
| **EU GDPR and UK GDPR** | Any user or customer in the EU/UK | Art. 6 lawful bases, Art. 7(1) proof of consent, Art. 12–22 rights, Art. 28 processor terms, Art. 33–34 breaches, Chapter V transfers |
| **Kenya Data Protection Act 2019** | Users in Kenya | Rights, transfers, registration with the ODPC |
| **Ghana Data Protection Act 2012** | Users in Ghana | Registration with the Data Protection Commission, rights |
| **South Africa POPIA** | Users in South Africa | Conditions for lawful processing, Information Regulator |
| **ISO/IEC 27001 and 27701** | Good practice, not a legal duty | Security and privacy controls. **We follow them; we are not certified**, and the policy says only "follows" |
| **PCI DSS** | Card payments | Handled by Flutterwave. Card numbers are typed on their page; VendSlip never sees or stores them |
| **Nigeria Federal Competition and Consumer Protection Act 2018** | Consumer-facing terms | Refunds, fair terms. VendSlip is B2B, but mandatory consumer rights are expressly preserved |
| **Nigerian tax and e-invoicing rules** | Merchants' own invoices | Merchants stay responsible for their tax and e-invoicing duties; VendSlip's verification code is not a tax certificate |

The Privacy Policy names the complaint authorities: NDPC (Nigeria), the merchant's EU authority, the UK ICO, Kenya's ODPC, Ghana's Data Protection Commission and South Africa's Information Regulator.

## How we apply them, area by area

Each row is a legal duty, what the policy promises, and how the product actually delivers it. **Manual** means a person at VendSlip has to do it; nothing automates it yet.

| Area | What the policy says | How it works in the product |
| --- | --- | --- |
| **Lawful basis** | Contract for the service and billing; legal obligation for tax records; legitimate interests for security, service emails and anonymised stats; owner consent for support sessions | A purpose-by-basis table is published in the Privacy Policy |
| **Proof of acceptance** (GDPR Art. 7(1), NDPA) | People accept the Terms and Privacy Policy before using or paying | Required tick box at sign-up, when joining a team, and before **every** payment; the server refuses the request without it. Each acceptance is stored with version, date, IP, browser and context (sign-up, invitation, update, checkout + payment reference) |
| **Changes to terms** | Owners told in advance; acceptance required from the effective date | Super-Admin publishes a version with an effective date and a "what changed" note. Owners get email + in-app notice. From the effective date, users must accept before making changes; reads still work |
| **Transparency** | Who we are, what we collect, why, sharing, transfers, retention, rights, complaints | Public `/privacy` page, versioned and dated |
| **Data subject rights** | Access, correction, deletion, restriction, objection, portability, withdrawing consent; reply within 30 days | Users can edit their profile and business details. **Manual:** access, export, deletion and objection requests go to the privacy email |
| **Processor duties** (GDPR Art. 28, NDPA s.29) | Act on instructions, confidentiality, security, sub-processors, help with requests and breaches, delete or return, audits | Written into the Terms' Data processing section. Merchants can export slips as CSV |
| **Retention** | Account data deleted within 90 days of closure; financial records 6 years; logs and tickets 24 months; unused chat drafts expire | Chat drafts expire automatically (hourly job). **Manual:** account deletion after closure, log and ticket purges |
| **Security** | ISO 27001/27701-aligned controls | HTTPS; hashed passwords and strong password rules; two-factor sign-in; gateway keys encrypted at rest; role-based access; audit log of changes; tamper-evident slip verification codes; alerts on payout-account changes; webhook signatures checked |
| **Staff access** | Support only with the owner's permission | Owner shares a one-time code; session is time-limited, logged, and cannot change passwords, 2FA, team access or payout accounts |
| **Breaches** | Authority within 72 h; affected people without undue delay | Security log flags critical events (bad webhook signatures, amount mismatches). **Manual:** the notification process itself |
| **International transfers** | Adequacy, standard contractual clauses or other recognised safeguards | Providers outside Nigeria: Meta, Telegram, Twilio, NOWPayments, email and hosting. **Not yet done:** signed transfer safeguards on file |
| **Sub-processors** | Listed by category in the Privacy Policy | Flutterwave, NOWPayments, Meta, Telegram, Twilio, email and hosting providers. The exchange-rate service gets no personal data |
| **Children** | Service is for 18+ | Stated in both documents; no age check |
| **Cookies** | Only strictly necessary: session and CSRF cookies, small preferences in browser storage; no ads or tracking | No cookie banner, which is correct only while that stays true |
| **Anonymous website notes** | "A business in Ikeja just issued a receipt": no names or amounts | Off by default; real events only; each business can opt out in Settings |
| **Payments to VendSlip** | Prepaid, no auto-renewal, no charge without action | No stored cards; reminders before a plan ends; plan changes credit unused days |
| **Merchants' customer payments** | VendSlip doesn't hold or settle that money | Each merchant uses their own gateway keys; VendSlip only redirects and records the result |

## Key positions in the Terms of Service

These are the commercial and legal positions a lawyer should test. Items marked **(business choice)** were drafted by us, not required by law.

| Topic | Position in the Terms |
| --- | --- |
| Parties and eligibility | Agreement with Techberryz Futurecore Limited; users 18+; business use only |
| How acceptance happens | Creating an account, joining a team, or ticking the box before payment; record kept of version, time and device |
| Merchant responsibility | Merchants own slip content, prices, VAT and taxes, and must have a lawful basis to give us customer details and to message customers |
| Plans | Prepaid monthly or yearly; **never auto-renew**; reminders before expiry; lapsed accounts move to Free with nothing deleted |
| Plan changes | Unused value of the old plan becomes extra time on the new one **(business choice)** |
| Refunds | Non-refundable except: double or wrong charges, or long outages caused by us, if reported within **14 days**; unused credit packs refundable within **14 days** of purchase **(business choice)** |
| Rejecting updated terms | User may stop and ask for a **pro-rata refund** of the unused paid period **(business choice)** |
| Feature changes | Pro-rata refund if a change materially reduces a paid feature mid-term **(business choice)** |
| Chargebacks | Contact us first; unjustified chargebacks may lead to suspension |
| Customer payments | Through the merchant's own gateway account; VendSlip is not a party to the sale and holds no funds |
| Data processing terms | GDPR Art. 28 / NDPA s.29 clauses built into the Terms (no separate DPA document) |
| Intellectual property | VendSlip owns the platform; merchants keep their content and grant a limited licence to host it |
| Suspension and closure | Close any time on request; data export on request within **30 days**; suspension for serious breach, fraud or non-payment, with notice where reasonable |
| Liability | No indirect or consequential loss; cap = **fees paid in the previous 12 months**; carve-outs for death, injury, fraud; merchant indemnity for unlawful use |
| Law and disputes | Nigerian law; 30 days of good-faith negotiation, then **Lagos State courts**; consumers keep mandatory home-country protections |

## Evidence: what is built into the product today

If a regulator or court asks us to prove something, these are the records we can produce now.

| Evidence | What it proves | Where it is kept |
| --- | --- | --- |
| Legal acceptance records | Who accepted which version of each document, when, from which IP and browser, and in what context (with the payment reference for checkouts) | `legal_acceptances` table; latest acceptance shown on each business's admin page |
| Version history of the documents | Which wording applied on which date, the change summary, who published it and when | Super-Admin → Terms & Privacy → Versions |
| Security audit log | Sign-ins, failed sign-ins, settings changes, payout-account changes, support sessions, bad webhook signatures, payment mismatches, terms published or accepted | Super-Admin → Security Logs; each business sees its own Activity log |
| Slip revision history | Every edit to an issued slip, with the reason and the person, under the business's chosen edit policy | Each slip's history in the dashboard |
| Slip verification codes | A slip has not been altered since it was issued | Public verification page linked from each PDF's QR code |
| Payment records | Amount, currency, gateway, status and reference of every payment to VendSlip | Super-Admin → Payments; Billing page per business |
| Support session records | Owner's approval code, staff member, start and end times, everything done in the session | Activity log and Security Logs |
| Notification log | Renewal reminders and terms-update notices sent, with dates | In-app notifications; reminder log per business |

All records sit in VendSlip's own PostgreSQL database. Backups and the hosting location are decided at deployment and are not yet documented.

## Gaps, assumptions and decisions to confirm

The policies promise some things that are not yet built or documented. Each one is either a promise to keep manually, a document to prepare, or wording the lawyer may want to change.

| # | Gap or assumption | Why it matters | Suggested action |
| --- | --- | --- | --- |
| 1 | **NDPC registration and annual audit.** Not registered. Under GAID 2025, controllers processing data of more than 200 people in six months are "of major importance" and must register; higher tiers file an annual Compliance Audit Return by 31 March through a licensed DPCO | Not filing: up to 2% of annual gross revenue or ₦10m, whichever is greater ([summary](https://stackweaver.cloud/insights/ndpc-compliance-audit-return/)) | Confirm our tier and register before launch |
| 2 | **No named Data Protection Officer.** Only a privacy email | NDPA and GAID expect a DPO for controllers of major importance | Appoint one (can be external) and name them in the policy |
| 3 | **Placeholder contact details.** privacy@vendslip.com, support@vendslip.com and "Lagos, Nigeria" | A policy must give a real, working contact and address | Set real mailboxes and the registered address (`.env` settings) |
| 4 | **Rights requests are manual.** No self-service export or account deletion | 30-day deadline under GDPR and NDPA | Write an internal procedure, or build export and delete |
| 5 | **Deletion within 90 days of closure is manual** | A promise in the Privacy Policy; breaking it is a breach | Procedure now; automate later |
| 6 | **Log and ticket purge after 24 months is manual** | Same | Same |
| 7 | **No signed transfer safeguards or processor contracts on file** (Meta, Telegram, Twilio, NOWPayments, Flutterwave, email, hosting) | GDPR Chapter V and NDPA Part VIII require them; the policy says we rely on them | Collect each provider's DPA and transfer terms; list sub-processors by name |
| 8 | **No records of processing (ROPA) and no impact assessment (DPIA)** | Required documents under GDPR Art. 30/35 and GAID | Prepare both; this brief is a starting point |
| 9 | **No written breach-response plan** | 72-hour notice duty | One-page plan: who decides, who notifies NDPC, templates |
| 10 | **EU/UK users without a local representative** | GDPR Art. 27 may require an EU/UK representative if we target those markets | Decide whether we market there; if so, appoint one |
| 11 | **Kenya and Ghana registration** | Both regulators require data handlers to register | Only when we actively serve those countries |
| 12 | **Refund rules** (14-day windows; credit-pack refunds; pro-rata refund when rejecting updated terms or losing a paid feature) | Business choices with cost; must fit the FCCPA | Confirm or change |
| 13 | **Liability cap = 12 months' fees** | Enforceability against small businesses and consumers | Confirm |
| 14 | **No separate Data Processing Agreement.** Art. 28 terms sit inside the Terms of Service | Larger customers may ask for a signed DPA | Decide whether to offer one on request |
| 15 | **Tax-authority name.** The Terms mention "FIRS"; Nigerian e-invoicing is now being rolled out under the Nigeria Revenue Service | Accuracy | Update wording at the next version |
| 16 | **Crypto payments** for plans through NOWPayments | Digital assets are now regulated by the SEC under the Investments and Securities Act 2025 | Confirm that accepting crypto via a third-party processor needs no licence |
| 17 | **VAT on VendSlip's own fees.** The Terms say taxes are added where required, but checkout does not add VAT | Our own VAT obligations as a seller of digital services | Confirm whether prices are VAT-inclusive and say so |
| 18 | **No age check.** 18+ is stated, not verified | Low risk for a B2B service | Confirm the statement is enough |
| 19 | **Not ISO-certified.** Policy says we "follow" ISO 27001/27701 | Overstating would mislead | Keep wording as is unless certified |
| 20 | **Hosting location and backups not documented** | Needed for the transfer section and the ROPA | Record once production hosting is chosen |

## Questions to take to the lawyer

Ask these in this order; the first five decide what we must do before launch.

1. Which NDPC tier are we in today, and when must we register and file a Compliance Audit Return? Do we need a licensed DPCO?
2. Must we appoint a DPO now? Can it be an external provider?
3. Is a controller/processor split, with Art. 28 / s.29 terms inside the Terms of Service, enough, or should we offer a signed DPA?
4. Which transfer mechanism should we rely on for each overseas provider, and what must we keep on file?
5. Is a tick box with a stored record (version, time, IP, browser, context) adequate proof of acceptance, including for updated terms enforced from an effective date?
6. Are the refund rules, the 12-month liability cap and the indemnity enforceable under Nigerian law and the FCCPA for small businesses?
7. Should we promise a pro-rata refund when someone rejects updated terms or loses a paid feature, or narrow those promises?
8. Is our approach to merchants' customer data right? Merchants give us customers' details and VendSlip sends them receipts, reminders and payment links on the merchant's behalf.
9. Does accepting crypto for plans through NOWPayments need any SEC registration or approval under the ISA 2025?
10. Do we charge VAT on plans and credit packs, and should prices shown be VAT-inclusive?
11. Do we need an EU/UK representative, or Kenya and Ghana registrations, if we accept sign-ups from those countries without marketing there?
12. Is "follows ISO/IEC 27001 and 27701" acceptable wording without certification?
13. Are our retention periods right: 6 years for financial records, 90 days after closure, 24 months for logs and tickets?
14. Is the anonymous "a business in Ikeja just issued a receipt" website feature fine on legitimate interests with an opt-out, or does it need opt-in consent?
15. Is the merchant-responsibility wording on tax and e-invoicing strong enough as the Nigeria Revenue Service extends e-invoicing to smaller businesses?

## Appendix: where things live

| Item | Location |
| --- | --- |
| Terms of Service | `/terms` on the VendSlip website |
| Privacy Policy | `/privacy` on the VendSlip website |
| Current version and change history | Super-Admin → Terms & Privacy |
| First version, company name, address, privacy and support emails | `Backend/.env`: `VENDSLIP_LEGAL_VERSION`, `VENDSLIP_LEGAL_ENTITY`, `VENDSLIP_LEGAL_ADDRESS`, `VENDSLIP_PRIVACY_EMAIL`, `VENDSLIP_SUPPORT_EMAIL` |
| Policy wording (to edit after review) | `Frontend/src/pages/public/Legal.tsx` |
| Acceptance records | Database table `legal_acceptances`; latest per business on its admin page |
| Security and audit logs | Super-Admin → Security Logs |
| Payment records | Super-Admin → Payments |
| Renewal reminder settings | Super-Admin → Pricing Engine → Plan renewal reminders; per business on its admin page |
| Slip edit policy per business | Super-Admin → Merchants → the business → Ledger mutability |

**After the lawyer's review:** change the wording in `Legal.tsx`, deploy it, then publish a new version from Super-Admin → Terms & Privacy with a short summary and an effective date 14–30 days ahead. Owners are told by email and in the app, and everyone accepts before carrying on.

### Sources

- [NDPC Compliance Audit Return: who files and when](https://stackweaver.cloud/insights/ndpc-compliance-audit-return/)
- [NDPC Compliance Audit Returns deadline, May 2026](https://www.deepidv.com/media/minutes/ndpc-nigeria-compliance-audit-returns-deadline-may-2026)
- [FIRS e-invoicing for large taxpayers (Gazette)](https://gazettengr.com/firs-begins-e-invoicing-electronic-fiscal-system-for-large-taxpayers/)
