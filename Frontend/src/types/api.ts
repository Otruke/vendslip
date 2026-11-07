import type { BillingCycle, Channel, DocumentStatus, DocumentType, MutabilityMode, PaymentGateway, PaymentMethod, PaymentPurpose, PaymentStatus, PlanTier } from './generated'

export type Paginated<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
}

export type Merchant = {
  id: string
  name: string
  slug: string
  email: string | null
  phone: string | null
  address: string | null
  registration_type: RegistrationType | null
  rc_number: string | null
  country_code: string | null
  state: string | null
  city: string | null
  show_in_activity_feed: boolean
  tax_id: string | null
  currency: string
  vat_rate_bps: number
  vat_inclusive: boolean
  primary_color: string
  secondary_color: string
  logo_url: string | null
  receipt_footer: string | null
  bank_name: string | null
  bank_account_number: string | null
  bank_account_name: string | null
  invoice_due_days: number
  default_receipt_template_id: number | null
  default_invoice_template_id: number | null
  plan_tier: PlanTier
  effective_tier: PlanTier
  billing_cycle: BillingCycle | null
  plan_expires_at: string | null
  paid_credits_balance: number
  record_mutability_mode: MutabilityMode
  allow_staff_edits: boolean
  mutable_window_hours: number
  status: 'ACTIVE' | 'SUSPENDED'
  onboarding_completed_at: string | null
  gateways: { flutterwave: boolean; flutterwave_public_key: string | null; nowpayments: boolean }
}

export type RegistrationType = 'RC' | 'BN' | 'UNREGISTERED'

export type Locales = {
  currencies: { code: string; name: string; symbol: string; digits: number }[]
  countries: { code: string; name: string; currency: string | null; vat: number | null }[]
}

export type AppNotification = { id: string; event: string | null; title: string; body: string; path: string | null; read_at: string | null; created_at: string }
export type NotificationsResponse = { unread: number; data: AppNotification[] }

export type AlertSettings = {
  events: { event: string; label: string; description: string; group: string; mandatory: boolean; locked_roles: string[]; roles: string[]; email: boolean }[]
  roles: { value: string; label: string }[]
}

export type PayoutPublic = {
  kind: 'BANK' | 'CRYPTO'
  label: string | null
  bank_name?: string | null
  account_number?: string | null
  account_name?: string | null
  currency?: string | null
  asset?: string
  network?: string
  wallet_address?: string
}
export type PayoutAccount = PayoutPublic & { id: number; show_on_slips: boolean; is_default: boolean; updated_at: string }
export type PayoutAccountsResponse = { data: PayoutAccount[]; crypto: Record<string, string[]>; max: number }

export type ReminderSettings = {
  settings: { customer_emails: boolean; days_before: number; on_due_date: boolean; overdue_every_days: number; overdue_max: number }
  available: boolean
}

export type ActivityItem = {
  id: number; event: string; label: string; severity: 'INFO' | 'WARNING' | 'CRITICAL'; actor: string | null; support_admin: string | null
  ip: string | null; device: string | null; details: Record<string, unknown> | null; document_id: string | null; created_at: string
}
export type ActivityResponse = { data: ActivityItem[]; meta: { current_page: number; last_page: number; total: number } }

export type SupportTicket = {
  reference: string; subject: string; category: string; category_label: string
  status: 'OPEN' | 'AWAITING_CUSTOMER' | 'RESOLVED' | 'CLOSED'; status_label: string
  opened_by: string | null; last_activity_at: string; created_at: string
  messages: { id: number; kind: 'CUSTOMER' | 'AGENT' | 'SYSTEM'; author: string | null; body: string; created_at: string }[]
}
export type SupportTicketList = { data: Omit<SupportTicket, 'messages'>[]; categories: Record<string, string> }

export type ActivityFeedEvent = { kind: 'RECEIPT' | 'INVOICE' | 'SIGNUP' | 'UPGRADE'; place: string; minutes_ago: number; plan?: string }
export type ActivityFeed =
  | { enabled: false }
  | { enabled: true; min_seconds: number; max_seconds: number; max_per_visit: number; events: ActivityFeedEvent[]; stats: { slips_this_week: number; businesses: number } | null }

export type Entitlements = Record<
  'custom_branding' | 'remove_watermark' | 'sales_summaries' | 'export' | 'multi_user' | 'payment_links' | 'inventory' | 'debt_reminders',
  boolean
>

export type Session = {
  user: {
    id: number
    name: string
    email: string
    phone: string | null
    avatar_url: string | null
    role: string | null
    role_label: string | null
    permissions: string[]
    two_factor_enabled: boolean
    is_platform_admin: boolean
    last_login_at: string | null
  }
  merchant: Merchant | null
  entitlements: Entitlements
  usage: { monthly_quota: number | null; free_used: number; free_remaining: number | null; paid_credits: number; period: string } | null
  /** Present while a VendSlip Super-Admin is in this account through an owner-approved support session. */
  support?: { admin_name: string; expires_at: string; return_url: string } | null
}

export type DocumentItem = { id: number; sku_id: string | null; description: string; quantity: number; unit_price_minor: number; list_price_minor: number | null; line_total_minor: number }

export type Doc = {
  id: string
  doc_type: DocumentType
  doc_number: string
  status: DocumentStatus
  channel: Channel
  currency: string
  customer_id: string | null
  customer_name: string
  customer_phone: string | null
  customer_email: string | null
  customer_address: string | null
  subtotal_minor: number
  discount_minor: number
  /** Item discounts (list price − selling price), already inside subtotal_minor. */
  line_discount_minor: number
  vat_rate_bps: number
  vat_inclusive: boolean
  tax_minor: number
  shipping_minor: number
  total_minor: number
  amount_paid_minor: number
  balance_due_minor: number
  consumed_paid_credit: boolean
  has_watermark: boolean
  revision_number: number
  verification_code: string
  verification_hash: string
  verify_url: string
  pay_url: string | null
  template_id: number | null
  parent_document_id: string | null
  notes: string | null
  due_date: string | null
  issued_at: string
  voided_at: string | null
  void_reason: string | null
  created_by?: string | null
  items?: DocumentItem[]
  payments?: { id: number; amount_minor: number; method: PaymentMethod; reference: string | null; paid_at: string; receipt_document_id: string | null }[]
  children?: { id: string; doc_type: DocumentType; doc_number: string; status: DocumentStatus; total_minor: number; issued_at: string }[]
  parent?: { id: string; doc_number: string; doc_type: DocumentType } | null
  revisions_count?: number
}

export type DocDetail = {
  data: Doc
  meta: { can_edit: boolean; edit_reason_required: boolean; edit_blocked_reason: string | null; mutability_mode: MutabilityMode; credited_minor: number }
}

export type Revision = {
  id: number
  revision_number: number
  action: 'EDIT' | 'VOID' | 'CREDIT_NOTE' | 'PAYMENT'
  edit_reason: string | null
  actor: string | null
  actor_ip: string | null
  created_at: string
  diff: Record<string, { before: unknown; after: unknown }>
}

export type Customer = {
  id: string
  name: string
  phone: string | null
  email: string | null
  address: string | null
  notes: string | null
  documents_count: number | null
  total_billed_minor: number | null
  outstanding_minor: number | null
  last_purchase_at: string | null
  created_at: string
}

export type Sku = {
  id: string
  sku: string
  name: string
  description: string | null
  unit_price_minor: number
  stock_quantity: number
  low_stock_threshold: number
  track_stock: boolean
  block_overselling: boolean
  is_active: boolean
  is_low_stock: boolean
}

export type Template = {
  id: number
  slug: string
  name: string
  description: string | null
  engine: 'blade' | 'html'
  supports_receipt: boolean
  supports_invoice: boolean
  min_plan_tier: PlanTier
  is_default: boolean
  is_locked: boolean
}

export type Overview = {
  range: string
  /** Totals below are in this currency only (money is never added across currencies). */
  currency: string
  currencies: string[]
  kpis: {
    settled_revenue_minor: number
    settled_revenue_change_pct: number | null
    receivables_minor: number
    overdue_count: number
    slips_issued: number
    api_calls_saved: number
    free_remaining: number | null
    monthly_quota: number | null
    paid_credits: number
  }
  series: { bucket: string; revenue_minor: number; invoiced_minor: number }[]
  channels: { channel: Channel; label: string; count: number; total_minor: number }[]
  recent: Doc[]
  debtors: { customer_id: string | null; customer_name: string; customer_phone: string | null; outstanding_minor: number; invoices: number }[]
  low_stock: Sku[]
}

export type Plan = {
  tier: PlanTier
  name: string
  tagline: string | null
  currency: string
  monthly_price_minor: number
  yearly_price_minor: number
  /** True when the price was converted from the base price at today's rate. */
  converted?: boolean
  monthly_quota: number | null
  highlights: string[]
  features: Record<string, boolean | number | null>
}

export type PublicPlans = {
  plans: Plan[]
  currency: string | null
  credit_packs: { id: number; name: string; credits: number; price_minor: number; currency: string; converted?: boolean }[]
  yearly_discount_label: string | null
  paid_credit_removes_watermark: boolean
}

export type Transaction = {
  reference: string
  purpose: PaymentPurpose
  gateway: PaymentGateway
  status: PaymentStatus
  amount_minor: number
  currency: string
  credits: number | null
  plan_tier: PlanTier | null
  billing_cycle: BillingCycle | null
  created_at: string
  paid_at: string | null
}

export type VerifyResult = {
  found: boolean
  authentic: boolean
  superseded: boolean
  current_revision: number
  document: {
    doc_type: DocumentType
    doc_number: string
    status: DocumentStatus
    currency: string
    total_minor: number
    amount_paid_minor: number
    balance_due_minor: number
    customer_display: string
    issued_at: string
    voided_at: string | null
    verification_code: string
    items_count: number
    is_mutable_record: boolean
  }
  merchant: { name: string; logo_url: string | null; primary_color: string; verified_since: string }
}

export type PublicInvoice = {
  doc_number: string
  status: DocumentStatus
  currency: string
  customer_name: string
  issued_at: string
  due_date: string | null
  items: { description: string; quantity: number; unit_price_minor: number; line_total_minor: number }[]
  subtotal_minor: number
  discount_minor: number
  tax_minor: number
  shipping_minor: number
  total_minor: number
  amount_paid_minor: number
  balance_due_minor: number
  verification_code: string
  verification_hash: string
  pdf_url: string
  merchant: {
    name: string
    logo_url: string | null
    primary_color: string
    secondary_color: string
    phone: string | null
    bank_name: string | null
    bank_account_number: string | null
    bank_account_name: string | null
    payouts: PayoutPublic[]
  }
  gateways: { flutterwave: boolean; nowpayments: boolean }
}

export type TeamResponse = {
  members: { id: number; name: string; email: string; phone: string | null; avatar_url: string | null; role: string | null; role_label: string | null; is_active: boolean; two_factor_enabled: boolean; last_login_at: string | null; can_manage: boolean; is_last_owner: boolean }[]
  invitations: { id: number; email: string; role: string; role_label: string; expires_at: string }[]
  seats: { used: number; max: number | null; can_invite: boolean }
  roles: { value: string; label: string }[]
}

export type ChannelsResponse = {
  channels: { channel: Channel; label: string; configured: boolean; slips_30d: number }[]
  identities: { id: number; channel: Channel; display_name: string | null; external_user_id: string; user_name: string; is_mine: boolean; linked_at: string }[]
  stats: { drafts_30d: number; confirmed_30d: number; api_calls_saved_30d: number }
  handles: {
    whatsapp_number: string | null
    whatsapp_link: string | null
    telegram_username: string | null
    telegram_link: string | null
    messenger_link: string | null
    instagram_username: string | null
    instagram_link: string | null
  }
}

export type ShareLinks = { pdf_url: string; verify_url: string; pay_url: string | null; message: string; whatsapp_url: string; telegram_url: string }
