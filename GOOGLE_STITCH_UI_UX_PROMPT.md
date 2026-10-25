# NetSlip — Google Stitch UI/UX Design Specification & Prompt Pack
> **Brand Identity Source:** `C:\Users\rufus.otolorin\Documents\Ampps\netSlip\brand_identity`  
> **UI/UX Aesthetic References:** Casho E-Invoice (`pin/978970037779180254`), Invoice2go (`pin/665688388643811563`), Business Billing Manager (`pin/988610555722404628`), Paper-Free Tactile Invoices (`pin/955818720923905617`)

---

## 1. Exact Brand Identity Specification (Extracted from `./brand_identity`)

When generating UI/UX in **Google Stitch**, upload these files from `C:\Users\rufus.otolorin\Documents\Ampps\netSlip\brand_identity` as **Reference Attachments**:
1. **`Netslip PNG.png`** — Official full transparent logo (Icon + Wordmark + Tagline).
2. **`Brand Guide.jpg`** — Official color swatches (`#377581` & `#265363`) and typography guide (**Lupio** & **Brush Script MT**).
3. **`DP 1.jpg`** — 3D tactile icon mark showing the exact white-sheet/teal-checkmark depth.

### 1.1 Complete Brand DNA Included in Every Stitch Prompt Below
* **Logo Mark Anatomy (`Netslip PNG.png` & `DP 1.jpg`)**:
  * A bold, geometric uppercase **`N`** in **Deep Slate Teal (`#265363`)** whose right vertical stem seamlessly forms a **folded-corner receipt/invoice document** with a crisp white (`#FFFFFF`) interior page.
  * Inside the white page are **4 rounded horizontal pill bars** (short, full, full, medium) in **Primary Teal (`#377581`)** representing transaction line items, with a bold **verification checkmark (`✓`)** in `#377581` at the bottom right.
* **Wordmark & Tagline Anatomy**:
  * **Wordmark**: **`NetSlip`** set in **Lupio ExtraBold / Black** in Deep Slate Teal (`#265363`) on light backgrounds (or pure white `#FFFFFF` on dark `#265363` headers).
  * **Tagline**: ***"Close the sale. Send the slip"*** set in **Brush Script MT** (italic script) in Primary Teal (`#377581`).
* **Official Color System (`Brand Guide.jpg` & `Netslip Mock 1–4.jpg`)**:
  * **Primary Brand Teal**: `#377581` — Primary CTA buttons, active navigation indicators, receipt table headers, interactive chips, and checkmark accents.
  * **Deep Slate Teal**: `#265363` — Sidebar navigation, executive summary hero card, receipt header band, and primary display headings.
  * **Architectural Canvas Neutrals (from `DP 1.jpg` & `Netslip Mock 1.jpg`)**: Warm Alabaster Canvas (`#F4F7F8`), Pure Paper White (`#FFFFFF`), Subtle Hairline Border (`#E2E8F0`), and Deep Charcoal Ink (`#111820` from `Netslip Mock 4.jpg`).
* **Official Typography (`Brand Guide.jpg`)**:
  * **Primary Font — `Lupio`** (Geometric Neo-Grotesque Sans-Serif, weights: Regular `400`, Medium `500`, SemiBold `600`, Bold `700`, ExtraBold `800`, Black `900` + tabular numerals for all currency figures).
  * **Secondary Font — `Brush Script MT`** (Expressive Script Font used *exclusively* for the brand tagline *"Close the sale. Send the slip"* and the receipt footer signature *"Thank you for your patronage!"*).

---

## 2. Copy-Paste Prompts for Google Stitch (With Full Brand Identity Embedded)

> **Tip for Google Stitch:** Upload `Netslip PNG.png` and `Brand Guide.jpg` into the Stitch prompt attachment box, then paste **Prompt 0** first, followed by **Prompts 1–7** for each screen. Every prompt below carries the self-contained brand identity block so Stitch never drifts into generic colors or fonts.

---

### PROMPT 0: Master Design System & App Shell Prompt (Paste First with `Netslip PNG.png` & `Brand Guide.jpg`)

```text
Design a sleek, tactile, real-world B2B Fintech SaaS web application and responsive mobile interface called "NetSlip" (Tagline: "Close the sale. Send the slip") — an Automated Receipt & Invoice Generator for WhatsApp, Instagram, Facebook, and Telegram sellers.

1. STRICT BRAND IDENTITY (MATCH THE UPLOADED NETSLIP LOGO & BRAND GUIDE EXACTLY):
- Logo Mark: Use the uploaded NetSlip logo (a bold geometric "N" in #265363 whose right vertical pillar transforms into a folded-corner white receipt page containing 4 horizontal #377581 line-item bars and a bottom-right #377581 checkmark, above the bold "NetSlip" wordmark and cursive "Close the sale. Send the slip" tagline).
- Official Brand Colors ONLY:
  • Primary Brand Teal: #377581 (CTAs, active pills, receipt table headers, links, focus rings, progress bars)
  • Deep Slate Teal: #265363 (Left sidebar navigation, executive balance card, receipt header band, bold headings)
  • Architectural Canvas Background: #F4F7F8 (Soft warm alabaster-slate background, like the clean white/off-white studio surface in the NetSlip 3D mockup)
  • Elevated Paper / Card Surface: #FFFFFF (Pure white cards with 1px solid #E2E8F0 hairline borders and soft tactile shadow 0 2px 10px rgba(38, 83, 99, 0.06))
  • Executive Dark Contrast Accent: #111820 (Deep matte charcoal for code/terminal drawers and high-contrast text)
  • Semantic Financial Statuses: Paid (#10B981 on #ECFDF5), Partial/Pending (#D97706 on #FFFBEB), Overdue (#E11D48 on #FFF1F2), Immutable Lock (#475569 on #F1F5F9).
- Official Brand Typography:
  • Primary Font: "Lupio" (clean geometric sans-serif with strict tabular numerals for all Naira ₦ / USD $ amounts, headings, tables, and buttons).
  • Secondary Accent Font: "Brush Script MT" (cursive script used ONLY for the brand tagline "Close the sale. Send the slip" under the logo and the decorative receipt footer "Thank you for your patronage!").

2. ANTI-GENERIC UI/UX GUARDRAILS (INSPIRED BY CASHO, INVOICE2GO & PAPER-FREE BILLING):
- DO NOT use generic AI purple/indigo/blue gradients, glowing neon borders, dark-mode cyberpunk dashboards, or stock 3D cartoon characters.
- Use a document-first, tactile paper aesthetic where receipts and invoices look like real, crisp, physical/digital slips with clean typography, right-aligned monetary columns, subtle perforation/sheet edges, and scannable QR verification blocks.

3. GLOBAL APPLICATION SHELL:
- Left Navigation Sidebar (260px wide, solid #265363 Deep Slate Teal):
  • Top Brand Lockup: White rounded badge holding the NetSlip "N" receipt-checkmark logo, "NetSlip" in white Lupio ExtraBold, and "Close the sale. Send the slip" in #8CD4E0 Brush Script MT cursive.
  • Active Store Card: "Amina Luxury Couture · Business Plus Plan" with a subtle #377581 border.
  • Navigation Items: Overview & Analytics, /Slash Slip Composer, Receipts & Invoices, Template Gallery, Customers & Debtors, Inventory (SKU), Team & Staff, Bot Channels (WA/TG/IG/FB), Billing & PAYG Credits.
  • Bottom Entitlement Widget: Shows "Unlimited Plan Active" + "50 Non-Expiring PAYG Credits" with a full-width "#377581" button: "+ Top Up Credits".
- Top Header Bar (64px height, #FFFFFF surface, 1px solid #E2E8F0 bottom border):
  • Quick Command Search ("Search RCP-2026-1001, customer name, or type /name = ..."), live channel status pills (WhatsApp via Twilio, Telegram Bot 10.3, Instagram, Facebook), and store owner profile.
```

---

### PROMPT 1: Business Dashboard — Executive Overview & Analytics Screen (React TS)

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Warm Alabaster Canvas #F4F7F8, Pure White Cards #FFFFFF, Lupio geometric sans-serif font, Brush Script MT cursive tagline, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Overview & Analytics" screen for the Merchant Dashboard.

VISUAL LAYOUT (CASHO & INVOICE2GO FINTECH CRAFTSMANSHIP):
1. Top 4-Card KPI Bento Grid:
   - Card 1 (Hero Executive Card in solid #265363 Deep Slate Teal with subtle #377581 top border accent):
     • Label: "Total Settled Revenue (Oct 2026)"
     • Value: "₦4,850,000.00" in 32px Lupio ExtraBold white tabular numerals
     • Sub-badge: "+18.4% vs last month · 94 Paid Receipts" in soft emerald pill
     • Action Buttons inside card: "[+ New Receipt]" (solid #377581 button) and "[+ New Invoice]" (translucent white-border button).
   - Card 2 (Pure White #FFFFFF Card):
     • Label: "Unpaid Invoices (Receivables)"
     • Value: "₦602,500.00" in #265363 bold numerals
     • Footer: "4 Overdue Invoices · Auto-Reminders Active" with link "Send Reminders →" in #377581.
   - Card 3 (Pure White #FFFFFF Card):
     • Label: "Subscription & PAYG Credit Wallet"
     • Value: "Unlimited Slips" + "50 PAYG Credits"
     • Footer: "Paid Credits Remove NetSlip Watermark Automatically".
   - Card 4 (Pure White #FFFFFF Card):
     • Label: "Twilio / Meta Cost-Saver Buffer"
     • Value: "142 Outbound API Calls Saved"
     • Footer: "Pre-Confirmation Gate Active (₦0 Wasted Bot Charges)".

2. Middle Analytics Section (12-Column Grid):
   - Left (8 Columns — White Card): "Settled Receipts vs. Unpaid Invoices" clean bar/area chart using #377581 (Settled Receipts) and #D97706 (Unpaid Receivables), with segmented period pills (7D, 30D, 90D, 12M).
   - Right (4 Columns — White Card): "Omnichannel Revenue Split" showing clean horizontal progress bars in shades of #265363 and #377581 for WhatsApp (52%), Telegram Bot 10.3 (28%), Instagram DM (14%), and Facebook Messenger (6%).

3. Bottom Split Section:
   - Left (7 Columns — Casho-Style Recent Slips List):
     • Segmented filter tabs: "All Slips", "Receipts", "Invoices", "Overdue".
     • Clean document rows with the NetSlip document icon, "RCP-2026-1004 · John Danjuma", Channel badge ("WhatsApp"), Issue Date, right-aligned bold Lupio amount ("₦15,000.00"), emerald "PAID" badge, and quick action icons (PDF Download, QR Verify, Share).
   - Right (5 Columns — Inventory Low-Stock & Overdue Debtors Card):
     • Shows low-stock SKUs ("SKU-BAG-03 Handcrafted Tote Bag · 3 left in stock") and top debtors with a 1-click "[Send Payment Link]" button in #377581.
```

---

### PROMPT 2: Split-Screen `/Slash` Bot Studio & Invoice2go-Style Live Slip Composer

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio primary font, Brush Script MT cursive accent font, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Omnichannel /Slash Composer & Live Paper Slip Preview" screen inspired by Invoice2go.

SPLIT-SCREEN WORKSPACE (55% Left Controls / 45% Right Live Paper Slip Preview):

LEFT PANE (55% Width — Transaction Input & Pre-Confirmation Studio):
1. Mode Selector Segmented Control:
   - "[⚡ /Slash Command Input (WhatsApp & Telegram Style)]" (Active in #377581)
   - "[📝 Structured Visual Form]"
2. Slash-Command Terminal Card:
   - Crisp card with #265363 header bar ("Type parameters together or one-by-one") and a clean monospace/Lupio input box containing:
     "/type = invoice, /name = Chief Emeka Nwosu, /phone = +2348039981122, /address = 42 Wuse II Crescent Abuja, /item = 2 x Leather Minimalist Sneakers @ 28500, /shipping = 3500, /tax = 7.5%, /paid = 20000"
   - Clickable Quick-Insert Parameter Chips below in soft teal (#F0FDFA with #377581 text): "+ /name =", "+ /amount =", "+ /address =", "+ /item =", "+ /discount =", "+ /tax =", "+ /shipping =", "+ /paid =".
3. Zero-Waste Pre-Confirmation Gate Card:
   - Explains that on WhatsApp/IG/FB (Twilio) and Telegram 10.3, partial fields buffer silently at ₦0 cost until the vendor clicks Confirm below.
4. Line Items, SKU Auto-Deduction & Payment Link Controls:
   - Compact table of parsed items linked to Inventory SKUs ("SKU-SNK-02 · Deducts 2 units from stock"), VAT 7.5% Exclusive/Inclusive switch, and checkboxes to attach "Flutterwave Online Payment Link" and "NOWPayments Crypto (USDT/USDC) Link".
5. Primary Action Footer:
   - "[✅ Confirm & Generate Branded PDF]" (solid #377581 button), "[🪙 Spend 1 Paid Credit (Remove Watermark)]" (#265363 button), and "[Reset Draft]".

RIGHT PANE (45% Width — Tactile Floating Paper Slip Preview):
- Top Preview Toolbar: Template selector pill ("Template: NetSlip Classic Executive ▾"), Mode badge ("INVOICE PREVIEW"), and "[⬇ Download PDF]" / "[🖨️ Print]".
- Realistic Floating Paper Document Sheet (#FFFFFF with realistic paper shadow):
  • Top Banner in #265363 Deep Slate Teal with a 6px #377581 bottom accent bar, Store Logo, "Amina Luxury Couture & Kicks", and right-aligned "INVOICE #INV-2026-5001" + amber "PARTIALLY PAID" stamp.
  • Two-column "Billed To" (Chief Emeka Nwosu, 42 Wuse II Abuja) and "Metadata" (Issued Date, Due Date, Channel: Telegram 10.3).
  • Lupio Tabular Grid with #377581 header bar (QTY, ITEM DESCRIPTION, UNIT PRICE, LINE TOTAL).
  • Totals Breakdown: Subtotal (₦57,000.00), VAT 7.5% (₦4,275.00), Shipping (₦3,500.00), Grand Total highlighted in a #265363 bar ("₦64,775.00"), Amount Paid ("₦20,000.00"), and Balance Due ("₦44,775.00").
  • Payment & Verification Footer: Bank Transfer details, embedded "[💳 Pay Balance via Flutterwave or USDT Crypto]" button in #377581, scannable QR code ("Verify: NSV-9A84F2..."), and the signature "Thank you for your patronage!" rendered in Brush Script MT (#377581).
```

---

### PROMPT 3: Casho-Style Documents Ledger & Admin-Controlled Mutable/Immutable Edit Drawer

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio font, Brush Script MT accent font, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Receipts, Invoices & Credit Notes Ledger" screen with a slide-over "Edit Document Drawer".

MAIN LEDGER VIEW:
1. Top Header & Admin Mutability Status Pill:
   - Left Title: "Financial Documents Ledger" in #265363 Lupio ExtraBold.
   - Right Policy Indicator (Show both states clearly in a status banner):
     • Active Badge: "✏️ Mutable Mode Enabled by Admin (Audited Edits Active)" in #F0FDFA / #377581 border, alongside a secondary preview badge showing how "🔒 Immutable Mode (Locked — Credit Note Only)" looks when toggled by the Super-Admin.
   - Action Buttons: "[📊 Export Excel / CSV]" (#265363) and "[+ New Slip]" (#377581).
2. Segmented Filter Bar (Casho Style):
   - Pills: "All Slips (128)", "Receipts (94)", "Invoices (30)", "Overdue (4)", "Credit Notes / Void (4)" + Search input and Channel filter (WhatsApp, Telegram, Instagram, Facebook).
3. Tactile Ledger Table (#FFFFFF card):
   - Columns: Document # & Verification Hash, Type Pill (RECEIPT / INVOICE), Customer & Address, Channel Badge, Grand Total (right-aligned Lupio bold), Balance Due, Status Badge (PAID, PARTIAL, OVERDUE, VOID), Watermark Status ("Clean · No Watermark" vs "Powered by NetSlip"), and Actions: "[Preview]", "[PDF]", "[✏️ Edit]", "[Credit Note]".

RIGHT SLIDE-OVER DRAWER ("Edit Issued Document #INV-2026-5001 — Revision #2"):
- Header in #265363: "Edit Record (Admin-Authorized Mutable Mode)"
- Audit Notice Box: "Your account is authorized by the Administrator to edit issued records. A full before/after snapshot will be saved in Revision History."
- Form inputs in Lupio font: Customer Name, Phone, Address, Editable Line Items (Qty, Item, Unit Price), Discount, Amount Paid, and a required field: "Audit Reason for Edit (e.g., Corrected customer delivery address & quantity)".
- Bottom Buttons: "[Save Changes & Re-Issue PDF (Rev #2)]" in #377581 and "[Cancel]".
```

---

### PROMPT 4: Template Gallery & Dual Default Templates Showcase (`Classic Executive` & `Minimalist Social Slip`)

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio font, Brush Script MT cursive tagline/signature font, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Invoice & Receipt Template Gallery & Brand Customizer" screen.

SCREEN LAYOUT:
1. Top Brand Customizer Strip (#FFFFFF Card):
   - Store Logo Uploader preview, Primary Color Swatch (#377581), Secondary Color Swatch (#265363), Font Preview ("Lupio + Brush Script MT"), and a live preview mode switch: "[Preview as: Paid Receipt]" vs "[Preview as: Unpaid Invoice]".

2. Prominent Showcase of the 2 Built-In Default Templates (Side-by-Side High-Detail Paper Previews):
   - DEFAULT TEMPLATE 1 CARD: "NetSlip Classic Executive" (Badge: "Default Corporate · Supports Invoice & Receipt")
     • Shows a detailed miniature A4 paper slip with a full-width #265363 header band, #377581 accent stripe, two-column customer/metadata block, zebra-striped Lupio item table, bank + Flutterwave/Crypto payment box, Brush Script MT "Thank you for your patronage!" signature, QR verification code, and optional bottom watermark bar ("Powered by NetSlip — Close the sale. Send the slip").
     • Footer Actions: "[✓ Selected for Invoices]" (solid #377581), "[Use for Receipts]", "[Customize Fields]".

   - DEFAULT TEMPLATE 2 CARD: "NetSlip Minimalist Social Slip" (Badge: "Default Mobile-First · Supports Invoice & Receipt")
     • Shows a sleek, vertical mobile-optimized receipt/invoice card designed for instant reading inside WhatsApp, Instagram, and Telegram chats without zooming. Centered logo, bold #265363 store title, crisp dashed horizontal dividers, stacked item rows, a high-contrast #377581 bordered Grand Total box, compact payment link button, centered QR verification code, and Brush Script MT thank-you line.
     • Footer Actions: "[✓ Selected for Receipts]" (solid #265363), "[Use for Invoices]", "[Customize Fields]".

   - TEMPLATE 3 CARD: "Admin-Published Custom Boutique Template" (Pro / Business Plus Tier badge).
```

---

### PROMPT 5: Subscriptions, Non-Expiring PAYG Credit Packs & Flutterwave + Crypto Checkout

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio font, Brush Script MT cursive tagline, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Plans, Non-Expiring PAYG Credit Packs & Payment Checkout" screen.

SCREEN SECTIONS:
1. 3 Subscription Tier Cards (Clean Fintech Pricing Grid):
   - Card 1: "Free Plan" — "₦0 / month"
     • For new sellers & low-volume vendors.
     • Includes: 15 receipts/month, WhatsApp & Telegram text-to-receipt, 2 default templates, carries the "Powered by NetSlip — Close the sale. Send the slip" watermark.
   - Card 2 (Featured Card with #377581 2px border & "#265363 Most Popular" ribbon): "Pro Merchant Plan" — "₦2,500 / month" or "₦24,000 / year"
     • For active social media sellers.
     • Includes: Unlimited PDF receipts & invoices, custom logo & brand colors, 100% watermark removal, automated WhatsApp sales summaries, Excel/CSV bookkeeping exports.
   - Card 3: "Business Plus Plan" — "₦6,000 / month" or "₦60,000 / year"
     • For growing stores & small teams.
     • Includes: Everything in Pro + Multi-user staff access (RBAC), embedded online payment links (Flutterwave + Crypto), basic SKU inventory tracking, and automated debt payment reminders.

2. Pay-As-You-Go Non-Expiring Credit Packs Banner (For Micro-Merchants Who Dislike Monthly Subscriptions):
   - Deep Slate Teal (#265363) header strip with 3 tactile credit pack cards:
     • "50 Credits Pack — ₦1,000" (Non-expiring · Automatically removes "Powered by NetSlip" watermark!)
     • "150 + 15 Bonus Credits Pack — ₦2,500" (Non-expiring · Watermark-Free)
     • "350 + 50 Bonus Credits Pack — ₦5,000" (Non-expiring · Watermark-Free)

3. Integrated Checkout Drawer / Card (Fiat + Reliable Crypto Gateway):
   - Payment Tab 1: "Pay with Flutterwave (NGN Card, Bank Transfer, USSD, Mobile Money)"
   - Payment Tab 2: "Pay with Cryptocurrency (NOWPayments — USDT TRC-20, USDT BEP-20, USDC, BTC, TON with locked NGN exchange rate)"
```

---

### PROMPT 6: Laravel Super-Admin & Admin Control Portal (`/admin/*`)

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio geometric sans-serif font, Brush Script MT tagline accent, and the uploaded NetSlip "N" receipt-checkmark logo), design the "Super-Admin & Admin Control Portal" (Laravel Backend Dashboard).

LAYOUT & MODULES:
1. Top Super-Admin Header (#265363 with #377581 accent):
   - Displays NetSlip Logo, "Super-Admin Command Center", Platform MRR, Total PAYG Credits Sold, Active Merchants, and Webhook HMAC Health ("100% Verified").

2. Main Module A — Client Management & Per-Client Mutable/Immutable Controller:
   - Merchant list table with an interactive inspection panel for "Amina Luxury Couture":
   - Prominent Control Card: "Client Dashboard Record Mutability Policy"
     • Segmented 3-Way Selector:
       1. [🔒 IMMUTABLE (Strict — Client cannot edit issued slips from their dashboard)]
       2. [✏️ MUTABLE WITH AUDIT (Selected in #377581 — Client can edit slips; logs before/after diff)]
       3. [🔓 MUTABLE UNRESTRICTED]
     • Toggles: "Allow Staff to Edit" (Off/On) and "Editable Time Window (48h / Unlimited)".
     • Forensic Diff Viewer Table: Shows Revision #2 on INV-2026-5001 ("Customer Address & Total edited by Owner · Before: ₦12,000 -> After: ₦15,000").

3. Main Module B — Admin Invoice & Receipt Template Builder Studio:
   - Shows the 2 Default Templates ("NetSlip Classic Executive" & "NetSlip Minimalist Social Slip") plus a "[+ Create New Template]" builder where the Admin configures layout blocks, placeholders, and assigns templates to plans or specific clients.

4. Main Module C — Dynamic Monetization & Watermark Rules Engine:
   - Live editable inputs for Plan Prices (₦0, ₦2,500/₦24,000, ₦6,000/₦60,000), Free Quota (15/mo), and Credit Packs (₦1,000 = 50 credits).
   - Master Switch: "[✓] Remove 'Powered by NetSlip' watermark automatically when a Free user consumes a paid PAYG credit".
```

---

### PROMPT 7: Public Customer Invoice Checkout (`/pay/{uid}`) & Cryptographic QR Verifier (`/verify/{hash}`)

```text
Using the exact NetSlip Brand Identity (Primary Teal #377581, Deep Slate Teal #265363, Lupio font, Brush Script MT cursive tagline/signature, and the uploaded NetSlip "N" receipt-checkmark logo), design the two public customer-facing screens side-by-side:

1. Left Screen — Hosted Customer Invoice Checkout (/pay/INV-2026-5001):
   - Displays the merchant's branded invoice slip at the top with itemized breakdown and "Balance Due: ₦44,775.00".
   - Dual Payment Box below:
     • "[💳 Pay ₦44,775.00 via Flutterwave (Card / Bank Transfer / USSD)]" (Primary #377581 button)
     • "[🪙 Pay with Crypto via NOWPayments (28.15 USDT TRC-20 / USDC / BTC / TON)]" (#265363 button with QR wallet preview).

2. Right Screen — Official Cryptographic Receipt Verification Portal (/verify/NSV-9A84F2...):
   - Top header in #265363 with the NetSlip "N" receipt-checkmark logo and cursive tagline "Close the sale. Send the slip".
   - Green verification banner: "✅ AUTHENTIC & UNALTERED RECEIPT — HMAC-SHA256 Signature Verified".
   - Clean metadata summary: Merchant Name, Document #RCP-2026-1001, Customer Name, Total Paid (₦15,000.00), Issued Timestamp, and "[⬇ Download Official Verified PDF]".
```
