---
name: Tactile Slip Modernism
colors:
  surface: '#f8f9ff'
  surface-dim: '#d4dae6'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eef4ff'
  surface-container: '#e8eefa'
  surface-container-high: '#e2e9f4'
  surface-container-highest: '#dce3ee'
  on-surface: '#151c24'
  on-surface-variant: '#40484a'
  inverse-surface: '#2a313a'
  inverse-on-surface: '#eaf1fd'
  outline: '#70797b'
  outline-variant: '#bfc8ca'
  surface-tint: '#266772'
  primary: '#185c68'
  on-primary: '#ffffff'
  primary-container: '#377581'
  on-primary-container: '#d0f6ff'
  inverse-primary: '#93d0dd'
  secondary: '#386475'
  on-secondary: '#ffffff'
  secondary-container: '#bae6fa'
  on-secondary-container: '#3d6879'
  tertiary: '#005d69'
  on-tertiary: '#ffffff'
  tertiary-container: '#297683'
  on-tertiary-container: '#cdf6ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#afecfa'
  primary-fixed-dim: '#93d0dd'
  on-primary-fixed: '#001f25'
  on-primary-fixed-variant: '#004e5a'
  secondary-fixed: '#bde9fd'
  secondary-fixed-dim: '#a1cde0'
  on-secondary-fixed: '#001f29'
  on-secondary-fixed-variant: '#1e4c5c'
  tertiary-fixed: '#a6eefc'
  tertiary-fixed-dim: '#8ad2df'
  on-tertiary-fixed: '#001f24'
  on-tertiary-fixed-variant: '#004e59'
  background: '#f8f9ff'
  on-background: '#151c24'
  surface-variant: '#dce3ee'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 36px
    fontWeight: '800'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '800'
    lineHeight: 36px
    letterSpacing: -0.015em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: '700'
    lineHeight: 30px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  currency-display:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  currency-cell:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  slip-accent-script:
    fontFamily: Brush Script MT, cursive
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.04em
  label-xs:
    fontFamily: Plus Jakarta Sans
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 14px
    letterSpacing: 0.06em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-lg: 1.5rem
  margin: 1rem
  margin-md: 1.5rem
  margin-lg: 2.5rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.875rem
  space-lg: 1.25rem
  space-xl: 2rem
  space-2xl: 3rem
---

## Brand & Style

This design system serves micro-merchants and high-velocity social commerce entrepreneurs operating across chat and DM channels (WhatsApp, Instagram, Facebook, and Telegram). The interface marries modern fintech precision with a tactile, physical paper receipts aesthetic. It projects reliability, legibility, and professional authority without enterprise stiffness.

The aesthetic philosophy bridges:
- **Tactile Paper Skeuomorphism & Print Precision:** Subtle perforated divider motifs, micro-shadows that lift digital slips off the neutral surface, and hairline border structures mimicking crisp parchment and thermal receipt slips.
- **Fintech Utility:** High-clarity tabular numerical layouts, rapid transaction micro-interactions, single-tap share shortcuts for conversational channels, and crisp structured data blocks.
- **Warm Conversational Humanity:** Handwritten signature accents and expressive cursive gratitude notes injected selectively into customer-facing slips and merchant milestone moments.

## Colors

The palette balances cool, measured aquatic teals against an ultra-clean neutral surface.

- **Primary Teal (`#377581`):** Applied to primary actions, interactive toggle states, generated slip headers, active channel badges, and brand anchors.
- **Deep Slate Teal (`#265363`):** Reserved for elevated structural navigation, high-contrast headings, active status badges, and emphasized financial summaries.
- **Tertiary Accent Teal (`#5098A5`):** Used for hover states, active indicator dots, link underlines, and soft tinted indicator chips.
- **Dark Neutral (`#111820`):** Pure contrast text layer for line-item totals, invoice item names, barcode lines, and primary typography.
- **Paper & Canvas Surfaces:**
  - Base background canvas: `#F4F7F8` (soft, cool off-white paper cast).
  - Slip and Card surface: `#FFFFFF` (pure thermal sheet white).
  - Hairline borders and micro-perforations: `#E2E9EB`.
  - Subtle slip divider rules: `#CBD7DB`.

## Typography

The typographic hierarchy is divided into three distinct roles:
1. **Primary Structural Sans (`Plus Jakarta Sans`):** Clean, geometric, and authoritative. Handles all product navigation, forms, metadata labels, and invoice tables.
2. **Tabular Numerics Rule:** Every price, SKU code, tax subtotal, and receipt total string must enforce `font-feature-settings: "tnum" 1` to guarantee vertical digit alignment across accounting rows.
3. **Accent Script (`Brush Script MT` / script fallback):** Exclusively reserved for brand taglines ("Close the sale. Send the slip"), personalized merchant sign-offs, and "Thank you for your purchase!" slip footers. Never use for structural UI labels, numerical data, buttons, or error states.

## Layout & Spacing

The layout adapts for quick phone-based invoice generation while maintaining full parity on desktop split-pane workspaces:
- **Mobile (< 768px):** Single-column stacked workflow. The bill generator controls sit on top, followed by a fixed bottom slip preview drawer with direct 1-tap dispatch buttons for chat apps. Outer margins are tight (`1rem`) to maximize screen width.
- **Tablet (768px - 1024px):** 8-column layout with 24px outer margins and dynamic column spanning (5 columns form input, 3 columns live slip rendering).
- **Desktop (> 1024px):** Fixed dual-workspace split layout:
  - Left panel: 12-column subgrid for customer data, itemization, discounts, and payment link generators.
  - Right panel: Pinned realistic receipt preview viewport on canvas `#F4F7F8` with real-time slip compilation.

## Elevation & Depth

Visual hierarchy uses physical sheet layering:

- **Canvas Tier (Elevation 0):** `#F4F7F8` matte background. Flat, no shadow.
- **Input & Form Tier (Elevation 1):** `#FFFFFF` cards with hairline outline `1px solid #E2E9EB` and an ultra-soft ambient contact shadow: `0 1px 3px rgba(17, 24, 32, 0.04), 0 1px 2px rgba(17, 24, 32, 0.02)`.
- **Generated Slip Sheet (Elevation 2):** Pure `#FFFFFF` surface lifted with an ambient paper drop shadow: `0 4px 16px -2px rgba(38, 83, 99, 0.08), 0 2px 6px -1px rgba(17, 24, 32, 0.04)`. Outlined with a hairline border `1px solid #DCE5E8`.
- **Overlays & Modals (Elevation 3):** Fixed dispatch trays and payment popovers with `0 12px 32px -4px rgba(17, 24, 32, 0.16)`.
- **Perforated Seams:** Simulated receipt tears use a dashed hairline rule `border-top: 1px dashed #CBD7DB` with concave circular masks (`radial-gradient`) on edge boundaries.

## Shapes

The design system uses soft, calculated edges (Level 1 / Soft, `0.25rem` / `4px` to `0.5rem` / `8px` max):
- **Receipt Slips:** `4px` radius or crisp squared corners at the bottom tear-edge.
- **Buttons & Input Fields:** `6px` radius (`rounded-md`), presenting a balanced, professional fintech structure.
- **Badges & Channel Chips:** `4px` radius for status tokens; pill-shaped (`9999px`) only for platform-specific chat target tags (e.g., Telegram, WhatsApp pills).

## Components

### Buttons
- **Primary Action (Send Slip / Generate):** Solid `#377581` fill with `#FFFFFF` text, `font-weight: 600`, `6px` border-radius, `0.625rem 1.25rem` padding. Subtle brightness decrease on hover (`#265363`) and micro-press scale (`0.98`).
- **Secondary Action:** Ghost white surface with `#265363` text and a precise `1px solid #CBD7DB` hairline border.
- **Chat Quick-Share Buttons:** Flat branded accents featuring the social channel icon (WhatsApp `#25D366`, Telegram `#229ED9`, Instagram `#E4405F`) accompanied by a subtle `#265363` label.

### Input Fields
- Background: `#FFFFFF`. Border: `1px solid #D0DBDE`. Height: `42px`. Text color: `#111820`. Focus state: border shifts to `#377581` with an ambient glow ring `0 0 0 3px rgba(55, 117, 129, 0.15)`.
- Currency Input: Prefix locked to currency symbol (`$`, `€`, `£`, `₦`) rendered in `#265363` with tabular numeral entry.

### Slips & Invoice Cards
- White `#FFFFFF` card featuring a subtle receipt header with the brand teal icon `#377581`.
- Items organized via clean row borders (`border-bottom: 1px solid #F0F4F5`).
- Total price summary anchored at the bottom with a bold, high-contrast dark box (`#111820`) or a dashed perforation divider.
- Script accent block: bottom left or footer displaying the signature gratitude text in cursive `Brush Script MT`.

### Chips & Status Tags
- **Paid / Cleared:** Light mint background (`rgba(55, 117, 129, 0.10)`), text `#265363`, `font-size: 11px`, uppercase, tracking `0.05em`.
- **Pending / In DM:** Warm tint (`#FFF7ED`), text `#C2410C`.
- **Channel Indicators:** Compact chip holding the platform icon, seller handle, and slip sequence number (`#NS-9041`).